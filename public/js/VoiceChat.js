/**
 * VoiceChat.js - WebRTC Voice Chat Module for StrategyBuzzer
 * Provides real-time voice communication for Duo, League Individual, and League Team modes
 * Uses Firebase Firestore for signaling (SDP offer/answer and ICE candidates exchange)
 * Supports mesh network for up to 5 simultaneous speakers (League Team)
 */

class VoiceChat {
    constructor(options = {}) {
        this.sessionId = options.sessionId || null;
        this.localUserId = options.localUserId || null;
        this.remoteUserIds = options.remoteUserIds || [];
        this.isHost = options.isHost || false;
        this.mode = options.mode || 'duo';
        this.db = options.db || null;
        
        this.localStream = null;
        this.peerConnections = {};
        this.remoteAudioElements = {};
        this.pendingCandidates = {};
        this.isMuted = true;
        this.isConnected = false;
        this.isInitialized = false;
        
        this.onSpeakingChange = options.onSpeakingChange || null;
        this.onConnectionChange = options.onConnectionChange || null;
        this.onRemoteSpeaking = options.onRemoteSpeaking || null;
        this.onError = options.onError || null;
        
        this.unsubscribers = [];
        this.audioContext = null;
        this.analyser = null;
        this.speakingCheckInterval = null;
        
        this.iceServers = [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' },
            { urls: 'stun:stun2.l.google.com:19302' },
            { urls: 'stun:stun3.l.google.com:19302' },
            { urls: 'stun:stun4.l.google.com:19302' }
        ];
        
        console.log(`[VoiceChat] Created for session ${this.sessionId}, user ${this.localUserId}, mode: ${this.mode}`);
    }
    
    async initialize() {
        if (this.isInitialized) {
            console.log('[VoiceChat] Already initialized');
            return true;
        }
        
        if (!this.db || !this.sessionId || !this.localUserId) {
            console.error('[VoiceChat] Missing required parameters:', {
                db: !!this.db,
                sessionId: this.sessionId,
                localUserId: this.localUserId
            });
            return false;
        }
        
        try {
            await this.setupFirestoreListeners();
            await this.registerAsParticipant();
            this.isInitialized = true;
            console.log('[VoiceChat] Initialized successfully for session', this.sessionId);
            return true;
        } catch (error) {
            console.error('[VoiceChat] Initialization error', error);
            if (this.onError) this.onError(error);
            return false;
        }
    }
    
    async registerAsParticipant() {
        try {
            const odUserId = String(this.localUserId);
            const voiceRef = this.db.collection('voiceSessions').doc(this.sessionId);
            await voiceRef.collection('participants').doc(odUserId).set({
                odUserId: this.localUserId,
                odUserIdStr: odUserId,
                micEnabled: false,
                listening: true,
                joinedAt: firebase.firestore.FieldValue.serverTimestamp()
            }, { merge: true });
            console.log('[VoiceChat] Registered as participant:', odUserId);
        } catch (error) {
            console.error('[VoiceChat] Register participant error', error);
        }
    }
    
    async enableMicrophone() {
        console.log('[VoiceChat] Enabling microphone...');
        try {
            this.localStream = await navigator.mediaDevices.getUserMedia({ 
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                } 
            });
            
            this.isMuted = false;
            this.setupSpeakingDetection();
            
            await this.addLocalStreamToPeers();
            await this.signalMicState(true);
            
            if (this.onConnectionChange) {
                this.onConnectionChange({ muted: false, connected: true });
            }
            
            console.log('[VoiceChat] Microphone enabled successfully');
            return true;
        } catch (error) {
            console.error('[VoiceChat] Microphone access error', error);
            if (this.onError) this.onError(error);
            return false;
        }
    }
    
    async disableMicrophone() {
        console.log('[VoiceChat] Disabling microphone...');
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => {
                track.stop();
            });
            this.localStream = null;
        }
        
        this.isMuted = true;
        this.stopSpeakingDetection();
        
        for (const [remoteUserId, pc] of Object.entries(this.peerConnections)) {
            const senders = pc.getSenders();
            const audioSender = senders.find(s => s.track && s.track.kind === 'audio');
            if (audioSender) {
                try {
                    await audioSender.replaceTrack(null);
                    console.log(`[VoiceChat] Removed audio track for peer ${remoteUserId}`);
                } catch (e) {
                    console.warn(`[VoiceChat] Could not remove track for ${remoteUserId}:`, e);
                }
            }
        }
        
        await this.signalMicState(false);
        
        if (this.onConnectionChange) {
            this.onConnectionChange({ muted: true, connected: this.isConnected });
        }
        
        console.log('[VoiceChat] Microphone disabled');
    }
    
    async toggleMicrophone() {
        if (this.isMuted) {
            return await this.enableMicrophone();
        } else {
            await this.disableMicrophone();
            return false;
        }
    }
    
    async setupFirestoreListeners() {
        const voiceRef = this.db.collection('voiceSessions').doc(this.sessionId);
        
        const doc = await voiceRef.get();
        if (!doc.exists) {
            await voiceRef.set({
                sessionId: this.sessionId,
                mode: this.mode,
                createdAt: firebase.firestore.FieldValue.serverTimestamp()
            });
            console.log('[VoiceChat] Created voice session document');
        }
        
        const localUserIdStr = String(this.localUserId);
        
        const participantsRef = voiceRef.collection('participants');
        const unsub1 = participantsRef.onSnapshot(snapshot => {
            snapshot.docChanges().forEach(change => {
                const participantId = change.doc.id;
                const data = change.doc.data();
                
                if (participantId === localUserIdStr) return;
                
                console.log(`[VoiceChat] Participant ${change.type}:`, participantId, data);
                
                if (change.type === 'added' || change.type === 'modified') {
                    if ((data.micEnabled || data.listening) && !this.peerConnections[participantId]) {
                        const myId = parseInt(this.localUserId);
                        const theirId = parseInt(participantId);
                        const shouldInitiate = myId < theirId;
                        console.log(`[VoiceChat] Creating peer connection with ${participantId}, I initiate: ${shouldInitiate}`);
                        this.createPeerConnection(participantId, shouldInitiate);
                    }
                }
                
                if (change.type === 'removed') {
                    console.log(`[VoiceChat] Participant left: ${participantId}`);
                    this.closePeerConnection(participantId);
                }
            });
        });
        this.unsubscribers.push(unsub1);
        
        const offersRef = voiceRef.collection('offers');
        const unsub2 = offersRef.where('to', '==', localUserIdStr).onSnapshot(snapshot => {
            snapshot.docChanges().forEach(async change => {
                if (change.type === 'added') {
                    const data = change.doc.data();
                    console.log(`[VoiceChat] Received offer from ${data.from}`);
                    await this.handleOffer(data.from, data.offer);
                    await change.doc.ref.delete().catch(e => console.warn('[VoiceChat] Delete offer error:', e));
                }
            });
        });
        this.unsubscribers.push(unsub2);
        
        const answersRef = voiceRef.collection('answers');
        const unsub3 = answersRef.where('to', '==', localUserIdStr).onSnapshot(snapshot => {
            snapshot.docChanges().forEach(async change => {
                if (change.type === 'added') {
                    const data = change.doc.data();
                    console.log(`[VoiceChat] Received answer from ${data.from}`);
                    await this.handleAnswer(data.from, data.answer);
                    await change.doc.ref.delete().catch(e => console.warn('[VoiceChat] Delete answer error:', e));
                }
            });
        });
        this.unsubscribers.push(unsub3);
        
        const candidatesRef = voiceRef.collection('iceCandidates');
        const unsub4 = candidatesRef.where('to', '==', localUserIdStr).onSnapshot(snapshot => {
            snapshot.docChanges().forEach(async change => {
                if (change.type === 'added') {
                    const data = change.doc.data();
                    await this.handleIceCandidate(data.from, data.candidate);
                    await change.doc.ref.delete().catch(e => console.warn('[VoiceChat] Delete ICE error:', e));
                }
            });
        });
        this.unsubscribers.push(unsub4);
        
        console.log('[VoiceChat] Firestore listeners setup complete');
    }
    
    async createPeerConnection(remoteUserId, isInitiator) {
        if (this.peerConnections[remoteUserId]) {
            console.log(`[VoiceChat] Peer connection already exists for ${remoteUserId}`);
            return this.peerConnections[remoteUserId];
        }
        
        console.log(`[VoiceChat] Creating RTCPeerConnection with ${remoteUserId}, initiator: ${isInitiator}`);
        
        const pc = new RTCPeerConnection({ iceServers: this.iceServers });
        this.peerConnections[remoteUserId] = pc;
        if (!this.pendingCandidates[remoteUserId]) {
            this.pendingCandidates[remoteUserId] = [];
        }
        
        pc.addTransceiver('audio', { direction: 'sendrecv' });
        console.log(`[VoiceChat] Added audio transceiver for ${remoteUserId}`);
        
        if (this.localStream) {
            const audioTrack = this.localStream.getAudioTracks()[0];
            if (audioTrack) {
                const senders = pc.getSenders();
                const audioSender = senders.find(s => s.track === null || (s.track && s.track.kind === 'audio'));
                if (audioSender) {
                    await audioSender.replaceTrack(audioTrack);
                    console.log(`[VoiceChat] Replaced track on existing sender for ${remoteUserId}`);
                } else {
                    pc.addTrack(audioTrack, this.localStream);
                    console.log(`[VoiceChat] Added audio track for ${remoteUserId}`);
                }
            }
        }
        
        pc.ontrack = (event) => {
            console.log(`[VoiceChat] Received remote track from ${remoteUserId}`, event.streams);
            if (event.streams && event.streams[0]) {
                this.handleRemoteTrack(remoteUserId, event.streams[0]);
            } else if (event.track) {
                const stream = new MediaStream([event.track]);
                this.handleRemoteTrack(remoteUserId, stream);
            }
        };
        
        pc.onicecandidate = async (event) => {
            if (event.candidate) {
                console.log(`[VoiceChat] Sending ICE candidate to ${remoteUserId}`);
                await this.sendIceCandidate(remoteUserId, event.candidate);
            }
        };
        
        pc.oniceconnectionstatechange = () => {
            console.log(`[VoiceChat] ICE state with ${remoteUserId}: ${pc.iceConnectionState}`);
        };
        
        pc.onconnectionstatechange = () => {
            console.log(`[VoiceChat] Connection state with ${remoteUserId}: ${pc.connectionState}`);
            if (pc.connectionState === 'connected') {
                this.isConnected = true;
                if (this.onConnectionChange) {
                    this.onConnectionChange({ muted: this.isMuted, connected: true });
                }
            } else if (pc.connectionState === 'disconnected' || pc.connectionState === 'failed') {
                console.log(`[VoiceChat] Connection ${pc.connectionState} with ${remoteUserId}, will retry...`);
                setTimeout(() => {
                    if (this.peerConnections[remoteUserId] && 
                        (this.peerConnections[remoteUserId].connectionState === 'disconnected' ||
                         this.peerConnections[remoteUserId].connectionState === 'failed')) {
                        this.closePeerConnection(remoteUserId);
                    }
                }, 5000);
            }
        };
        
        pc.onnegotiationneeded = async () => {
            console.log(`[VoiceChat] Negotiation needed with ${remoteUserId}, isInitiator: ${isInitiator}`);
        };
        
        if (isInitiator) {
            try {
                const offer = await pc.createOffer({ offerToReceiveAudio: true });
                await pc.setLocalDescription(offer);
                console.log(`[VoiceChat] Created and set local offer for ${remoteUserId}`);
                await this.sendOffer(remoteUserId, offer);
            } catch (error) {
                console.error('[VoiceChat] Error creating offer', error);
            }
        }
        
        return pc;
    }
    
    async handleOffer(fromUserId, offer) {
        console.log(`[VoiceChat] Handling offer from ${fromUserId}`);
        
        let pc = this.peerConnections[fromUserId];
        if (!pc) {
            pc = await this.createPeerConnection(fromUserId, false);
        }
        
        try {
            if (pc.signalingState !== 'stable' && pc.signalingState !== 'have-local-offer') {
                console.warn(`[VoiceChat] Unexpected signaling state for offer: ${pc.signalingState}`);
            }
            
            await pc.setRemoteDescription(new RTCSessionDescription(offer));
            console.log(`[VoiceChat] Set remote description (offer) from ${fromUserId}`);
            
            await this.processPendingCandidates(fromUserId);
            
            if (this.localStream) {
                const audioTrack = this.localStream.getAudioTracks()[0];
                if (audioTrack) {
                    const senders = pc.getSenders();
                    const audioSender = senders.find(s => s.track === null || (s.track && s.track.kind === 'audio'));
                    if (audioSender && !audioSender.track) {
                        await audioSender.replaceTrack(audioTrack);
                        console.log(`[VoiceChat] Added local track after receiving offer from ${fromUserId}`);
                    }
                }
            }
            
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            console.log(`[VoiceChat] Created and set local answer for ${fromUserId}`);
            await this.sendAnswer(fromUserId, answer);
        } catch (error) {
            console.error('[VoiceChat] Error handling offer', error);
        }
    }
    
    async handleAnswer(fromUserId, answer) {
        console.log(`[VoiceChat] Handling answer from ${fromUserId}`);
        
        const pc = this.peerConnections[fromUserId];
        if (!pc) {
            console.warn('[VoiceChat] No peer connection for answer from', fromUserId);
            return;
        }
        
        try {
            if (pc.signalingState !== 'have-local-offer') {
                console.warn(`[VoiceChat] Unexpected signaling state for answer: ${pc.signalingState}`);
                return;
            }
            
            await pc.setRemoteDescription(new RTCSessionDescription(answer));
            console.log(`[VoiceChat] Set remote description (answer) from ${fromUserId}`);
            
            await this.processPendingCandidates(fromUserId);
        } catch (error) {
            console.error('[VoiceChat] Error handling answer', error);
        }
    }
    
    async handleIceCandidate(fromUserId, candidate) {
        const pc = this.peerConnections[fromUserId];
        
        if (!pc) {
            console.log(`[VoiceChat] Queuing ICE candidate from ${fromUserId} (no peer connection yet)`);
            if (!this.pendingCandidates[fromUserId]) {
                this.pendingCandidates[fromUserId] = [];
            }
            this.pendingCandidates[fromUserId].push(candidate);
            return;
        }
        
        if (!pc.remoteDescription || !pc.remoteDescription.type) {
            console.log(`[VoiceChat] Queuing ICE candidate from ${fromUserId} (no remote description yet)`);
            if (!this.pendingCandidates[fromUserId]) {
                this.pendingCandidates[fromUserId] = [];
            }
            this.pendingCandidates[fromUserId].push(candidate);
            return;
        }
        
        try {
            await pc.addIceCandidate(new RTCIceCandidate(candidate));
            console.log(`[VoiceChat] Added ICE candidate from ${fromUserId}`);
        } catch (error) {
            console.error('[VoiceChat] Error adding ICE candidate', error);
        }
    }
    
    async processPendingCandidates(remoteUserId) {
        const pending = this.pendingCandidates[remoteUserId];
        if (!pending || pending.length === 0) return;
        
        const pc = this.peerConnections[remoteUserId];
        if (!pc || !pc.remoteDescription || !pc.remoteDescription.type) return;
        
        console.log(`[VoiceChat] Processing ${pending.length} pending ICE candidates for ${remoteUserId}`);
        
        const candidatesToProcess = [...pending];
        pending.length = 0;
        
        for (const candidate of candidatesToProcess) {
            try {
                await pc.addIceCandidate(new RTCIceCandidate(candidate));
            } catch (error) {
                console.warn('[VoiceChat] Error adding pending ICE candidate', error);
            }
        }
    }
    
    handleRemoteTrack(remoteUserId, stream) {
        console.log(`[VoiceChat] Setting up audio playback for ${remoteUserId}`);
        
        let audioEl = this.remoteAudioElements[remoteUserId];
        
        if (!audioEl) {
            audioEl = document.createElement('audio');
            audioEl.id = `remote-audio-${remoteUserId}`;
            audioEl.autoplay = true;
            audioEl.playsInline = true;
            audioEl.volume = 1.0;
            audioEl.style.display = 'none';
            document.body.appendChild(audioEl);
            this.remoteAudioElements[remoteUserId] = audioEl;
            console.log(`[VoiceChat] Created audio element for ${remoteUserId}`);
        }
        
        audioEl.srcObject = stream;
        
        const playPromise = audioEl.play();
        if (playPromise !== undefined) {
            playPromise.then(() => {
                console.log(`[VoiceChat] Audio playing from ${remoteUserId}`);
            }).catch(err => {
                console.warn('[VoiceChat] Autoplay blocked, adding click listener', err);
                const resumeAudio = () => {
                    audioEl.play().then(() => {
                        console.log(`[VoiceChat] Audio resumed for ${remoteUserId} after user interaction`);
                        document.removeEventListener('click', resumeAudio);
                    }).catch(e => console.warn('[VoiceChat] Still cannot play:', e));
                };
                document.addEventListener('click', resumeAudio, { once: true });
            });
        }
    }
    
    async sendOffer(toUserId, offer) {
        const voiceRef = this.db.collection('voiceSessions').doc(this.sessionId);
        await voiceRef.collection('offers').add({
            from: String(this.localUserId),
            to: String(toUserId),
            offer: { type: offer.type, sdp: offer.sdp },
            timestamp: firebase.firestore.FieldValue.serverTimestamp()
        });
        console.log(`[VoiceChat] Sent offer to ${toUserId}`);
    }
    
    async sendAnswer(toUserId, answer) {
        const voiceRef = this.db.collection('voiceSessions').doc(this.sessionId);
        await voiceRef.collection('answers').add({
            from: String(this.localUserId),
            to: String(toUserId),
            answer: { type: answer.type, sdp: answer.sdp },
            timestamp: firebase.firestore.FieldValue.serverTimestamp()
        });
        console.log(`[VoiceChat] Sent answer to ${toUserId}`);
    }
    
    async sendIceCandidate(toUserId, candidate) {
        const voiceRef = this.db.collection('voiceSessions').doc(this.sessionId);
        await voiceRef.collection('iceCandidates').add({
            from: String(this.localUserId),
            to: String(toUserId),
            candidate: candidate.toJSON(),
            timestamp: firebase.firestore.FieldValue.serverTimestamp()
        });
    }
    
    async signalMicState(enabled) {
        const voiceRef = this.db.collection('voiceSessions').doc(this.sessionId);
        await voiceRef.collection('participants').doc(String(this.localUserId)).set({
            odUserId: this.localUserId,
            micEnabled: enabled,
            timestamp: firebase.firestore.FieldValue.serverTimestamp()
        }, { merge: true });
        console.log(`[VoiceChat] Signaled mic state: ${enabled}`);
    }
    
    async addLocalStreamToPeers() {
        if (!this.localStream) {
            console.log('[VoiceChat] No local stream to add');
            return;
        }
        
        const audioTrack = this.localStream.getAudioTracks()[0];
        if (!audioTrack) {
            console.log('[VoiceChat] No audio track in local stream');
            return;
        }
        
        console.log(`[VoiceChat] Adding local audio track to ${Object.keys(this.peerConnections).length} peers`);
        
        for (const [remoteUserId, pc] of Object.entries(this.peerConnections)) {
            if (pc.connectionState === 'closed') {
                console.log(`[VoiceChat] Skipping closed connection ${remoteUserId}`);
                continue;
            }
            
            try {
                const senders = pc.getSenders();
                const audioSender = senders.find(s => s.track === null || (s.track && s.track.kind === 'audio'));
                
                if (audioSender) {
                    console.log(`[VoiceChat] Replacing track for ${remoteUserId}`);
                    await audioSender.replaceTrack(audioTrack);
                } else {
                    console.log(`[VoiceChat] Adding new track for ${remoteUserId}`);
                    pc.addTrack(audioTrack, this.localStream);
                }
            } catch (error) {
                console.error(`[VoiceChat] Error adding track to ${remoteUserId}:`, error);
            }
        }
    }
    
    setupSpeakingDetection() {
        if (!this.localStream) return;
        
        try {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            this.analyser = this.audioContext.createAnalyser();
            const microphone = this.audioContext.createMediaStreamSource(this.localStream);
            
            microphone.connect(this.analyser);
            this.analyser.fftSize = 512;
            
            const dataArray = new Uint8Array(this.analyser.frequencyBinCount);
            let isSpeaking = false;
            
            const checkVolume = () => {
                if (!this.analyser || this.isMuted) return;
                
                this.analyser.getByteFrequencyData(dataArray);
                const average = dataArray.reduce((a, b) => a + b) / dataArray.length;
                
                const nowSpeaking = average > 25;
                if (nowSpeaking !== isSpeaking) {
                    isSpeaking = nowSpeaking;
                    if (this.onSpeakingChange) {
                        this.onSpeakingChange(isSpeaking);
                    }
                }
            };
            
            this.speakingCheckInterval = setInterval(checkVolume, 100);
            console.log('[VoiceChat] Speaking detection started');
        } catch (error) {
            console.error('[VoiceChat] Speaking detection error', error);
        }
    }
    
    stopSpeakingDetection() {
        if (this.speakingCheckInterval) {
            clearInterval(this.speakingCheckInterval);
            this.speakingCheckInterval = null;
        }
        
        if (this.audioContext) {
            this.audioContext.close().catch(() => {});
            this.audioContext = null;
            this.analyser = null;
        }
        
        if (this.onSpeakingChange) {
            this.onSpeakingChange(false);
        }
    }
    
    closePeerConnection(remoteUserId) {
        console.log(`[VoiceChat] Closing connection with ${remoteUserId}`);
        
        const pc = this.peerConnections[remoteUserId];
        if (pc) {
            pc.close();
            delete this.peerConnections[remoteUserId];
        }
        
        if (this.pendingCandidates[remoteUserId]) {
            this.pendingCandidates[remoteUserId].length = 0;
        }
        
        const audioEl = this.remoteAudioElements[remoteUserId];
        if (audioEl) {
            audioEl.srcObject = null;
            audioEl.remove();
            delete this.remoteAudioElements[remoteUserId];
        }
        
        const remainingConnections = Object.keys(this.peerConnections).length;
        if (remainingConnections === 0) {
            this.isConnected = false;
            if (this.onConnectionChange) {
                this.onConnectionChange({ muted: this.isMuted, connected: false });
            }
        }
    }
    
    getConnectionStatus() {
        const status = {};
        for (const [remoteUserId, pc] of Object.entries(this.peerConnections)) {
            status[remoteUserId] = {
                connectionState: pc.connectionState,
                iceConnectionState: pc.iceConnectionState,
                signalingState: pc.signalingState
            };
        }
        return status;
    }
    
    async destroy() {
        console.log('[VoiceChat] Destroying...');
        
        this.unsubscribers.forEach(unsub => {
            try {
                unsub();
            } catch (e) {
                console.warn('[VoiceChat] Error unsubscribing:', e);
            }
        });
        this.unsubscribers = [];
        
        for (const remoteUserId of Object.keys(this.peerConnections)) {
            this.closePeerConnection(remoteUserId);
        }
        
        await this.disableMicrophone();
        
        try {
            const voiceRef = this.db.collection('voiceSessions').doc(this.sessionId);
            await voiceRef.collection('participants').doc(String(this.localUserId)).delete();
        } catch (error) {
            console.warn('[VoiceChat] Error cleaning up participant', error);
        }
        
        this.isConnected = false;
        this.isInitialized = false;
        console.log('[VoiceChat] Destroyed');
    }
}

if (typeof window !== 'undefined') {
    window.VoiceChat = VoiceChat;
}
