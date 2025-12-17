/**
 * VoiceChat.js - WebRTC Voice Chat Module for StrategyBuzzer
 * Provides real-time voice communication for Duo, League Individual, and League Team modes
 * Uses Firebase Firestore for signaling (SDP offer/answer and ICE candidates exchange)
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
        this.isMuted = true;
        this.isConnected = false;
        
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
            { urls: 'stun:stun2.l.google.com:19302' }
        ];
    }
    
    async initialize() {
        if (!this.db || !this.sessionId || !this.localUserId) {
            console.error('VoiceChat: Missing required parameters');
            return false;
        }
        
        try {
            await this.setupFirestoreListeners();
            await this.registerAsParticipant();
            console.log('VoiceChat: Initialized for session', this.sessionId);
            return true;
        } catch (error) {
            console.error('VoiceChat: Initialization error', error);
            if (this.onError) this.onError(error);
            return false;
        }
    }
    
    async registerAsParticipant() {
        try {
            const voiceRef = this.db.collection('voiceSessions').doc(this.sessionId);
            await voiceRef.collection('participants').doc(String(this.localUserId)).set({
                odUserId: this.localUserId,
                micEnabled: false,
                listening: true,
                timestamp: firebase.firestore.FieldValue.serverTimestamp()
            }, { merge: true });
        } catch (error) {
            console.error('VoiceChat: Register participant error', error);
        }
    }
    
    async enableMicrophone() {
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
            
            console.log('VoiceChat: Microphone enabled');
            return true;
        } catch (error) {
            console.error('VoiceChat: Microphone access error', error);
            if (this.onError) this.onError(error);
            return false;
        }
    }
    
    async disableMicrophone() {
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => {
                track.stop();
            });
            this.localStream = null;
        }
        
        this.isMuted = true;
        this.stopSpeakingDetection();
        
        await this.signalMicState(false);
        
        if (this.onConnectionChange) {
            this.onConnectionChange({ muted: true, connected: this.isConnected });
        }
        
        console.log('VoiceChat: Microphone disabled');
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
                participants: {},
                createdAt: firebase.firestore.FieldValue.serverTimestamp()
            });
        }
        
        const participantsRef = voiceRef.collection('participants');
        const unsub1 = participantsRef.onSnapshot(snapshot => {
            snapshot.docChanges().forEach(change => {
                const participantId = change.doc.id;
                const data = change.doc.data();
                
                if (participantId === String(this.localUserId)) return;
                
                if (change.type === 'added' || change.type === 'modified') {
                    if ((data.micEnabled || data.listening) && !this.peerConnections[participantId]) {
                        const shouldInitiate = this.localUserId < parseInt(participantId);
                        this.createPeerConnection(participantId, shouldInitiate);
                    }
                }
                
                if (change.type === 'removed') {
                    this.closePeerConnection(participantId);
                }
            });
        });
        this.unsubscribers.push(unsub1);
        
        const offersRef = voiceRef.collection('offers');
        const unsub2 = offersRef.where('to', '==', String(this.localUserId)).onSnapshot(snapshot => {
            snapshot.docChanges().forEach(async change => {
                if (change.type === 'added') {
                    const data = change.doc.data();
                    await this.handleOffer(data.from, data.offer);
                    await change.doc.ref.delete();
                }
            });
        });
        this.unsubscribers.push(unsub2);
        
        const answersRef = voiceRef.collection('answers');
        const unsub3 = answersRef.where('to', '==', String(this.localUserId)).onSnapshot(snapshot => {
            snapshot.docChanges().forEach(async change => {
                if (change.type === 'added') {
                    const data = change.doc.data();
                    await this.handleAnswer(data.from, data.answer);
                    await change.doc.ref.delete();
                }
            });
        });
        this.unsubscribers.push(unsub3);
        
        const candidatesRef = voiceRef.collection('iceCandidates');
        const unsub4 = candidatesRef.where('to', '==', String(this.localUserId)).onSnapshot(snapshot => {
            snapshot.docChanges().forEach(async change => {
                if (change.type === 'added') {
                    const data = change.doc.data();
                    await this.handleIceCandidate(data.from, data.candidate);
                    await change.doc.ref.delete();
                }
            });
        });
        this.unsubscribers.push(unsub4);
    }
    
    async createPeerConnection(remoteUserId, isInitiator) {
        if (this.peerConnections[remoteUserId]) {
            return this.peerConnections[remoteUserId];
        }
        
        console.log(`VoiceChat: Creating peer connection with ${remoteUserId}, initiator: ${isInitiator}`);
        
        const pc = new RTCPeerConnection({ iceServers: this.iceServers });
        this.peerConnections[remoteUserId] = pc;
        
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => {
                pc.addTrack(track, this.localStream);
            });
        }
        
        pc.ontrack = (event) => {
            console.log(`VoiceChat: Received remote track from ${remoteUserId}`);
            this.handleRemoteTrack(remoteUserId, event.streams[0]);
        };
        
        pc.onicecandidate = async (event) => {
            if (event.candidate) {
                await this.sendIceCandidate(remoteUserId, event.candidate);
            }
        };
        
        pc.onconnectionstatechange = () => {
            console.log(`VoiceChat: Connection state with ${remoteUserId}: ${pc.connectionState}`);
            if (pc.connectionState === 'connected') {
                this.isConnected = true;
                if (this.onConnectionChange) {
                    this.onConnectionChange({ muted: this.isMuted, connected: true });
                }
            } else if (pc.connectionState === 'disconnected' || pc.connectionState === 'failed') {
                this.closePeerConnection(remoteUserId);
            }
        };
        
        if (isInitiator) {
            try {
                const offer = await pc.createOffer({ offerToReceiveAudio: true });
                await pc.setLocalDescription(offer);
                await this.sendOffer(remoteUserId, offer);
            } catch (error) {
                console.error('VoiceChat: Error creating offer', error);
            }
        }
        
        return pc;
    }
    
    async handleOffer(fromUserId, offer) {
        console.log(`VoiceChat: Handling offer from ${fromUserId}`);
        
        let pc = this.peerConnections[fromUserId];
        if (!pc) {
            pc = await this.createPeerConnection(fromUserId, false);
        }
        
        try {
            await pc.setRemoteDescription(new RTCSessionDescription(offer));
            
            if (this.localStream) {
                const existingSenders = pc.getSenders();
                const existingTracks = existingSenders.map(s => s.track).filter(Boolean);
                this.localStream.getTracks().forEach(track => {
                    if (!existingTracks.includes(track)) {
                        pc.addTrack(track, this.localStream);
                    }
                });
            }
            
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            await this.sendAnswer(fromUserId, answer);
        } catch (error) {
            console.error('VoiceChat: Error handling offer', error);
        }
    }
    
    async handleAnswer(fromUserId, answer) {
        console.log(`VoiceChat: Handling answer from ${fromUserId}`);
        
        const pc = this.peerConnections[fromUserId];
        if (!pc) {
            console.warn('VoiceChat: No peer connection for answer');
            return;
        }
        
        try {
            await pc.setRemoteDescription(new RTCSessionDescription(answer));
        } catch (error) {
            console.error('VoiceChat: Error handling answer', error);
        }
    }
    
    async handleIceCandidate(fromUserId, candidate) {
        const pc = this.peerConnections[fromUserId];
        if (!pc) {
            console.warn('VoiceChat: No peer connection for ICE candidate');
            return;
        }
        
        try {
            await pc.addIceCandidate(new RTCIceCandidate(candidate));
        } catch (error) {
            console.error('VoiceChat: Error adding ICE candidate', error);
        }
    }
    
    handleRemoteTrack(remoteUserId, stream) {
        let audioEl = this.remoteAudioElements[remoteUserId];
        
        if (!audioEl) {
            audioEl = document.createElement('audio');
            audioEl.id = `remote-audio-${remoteUserId}`;
            audioEl.autoplay = true;
            audioEl.playsInline = true;
            audioEl.style.display = 'none';
            document.body.appendChild(audioEl);
            this.remoteAudioElements[remoteUserId] = audioEl;
        }
        
        audioEl.srcObject = stream;
        audioEl.play().catch(err => {
            console.warn('VoiceChat: Autoplay blocked, user interaction needed', err);
        });
        
        console.log(`VoiceChat: Playing audio from ${remoteUserId}`);
    }
    
    async sendOffer(toUserId, offer) {
        const voiceRef = this.db.collection('voiceSessions').doc(this.sessionId);
        await voiceRef.collection('offers').add({
            from: String(this.localUserId),
            to: String(toUserId),
            offer: { type: offer.type, sdp: offer.sdp },
            timestamp: firebase.firestore.FieldValue.serverTimestamp()
        });
    }
    
    async sendAnswer(toUserId, answer) {
        const voiceRef = this.db.collection('voiceSessions').doc(this.sessionId);
        await voiceRef.collection('answers').add({
            from: String(this.localUserId),
            to: String(toUserId),
            answer: { type: answer.type, sdp: answer.sdp },
            timestamp: firebase.firestore.FieldValue.serverTimestamp()
        });
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
    }
    
    async addLocalStreamToPeers() {
        if (!this.localStream) return;
        
        for (const [remoteUserId, pc] of Object.entries(this.peerConnections)) {
            if (pc.connectionState === 'closed') continue;
            
            const senders = pc.getSenders();
            const audioTrack = this.localStream.getAudioTracks()[0];
            
            if (audioTrack) {
                const audioSender = senders.find(s => s.track && s.track.kind === 'audio');
                
                if (audioSender && audioSender.track) {
                    console.log(`VoiceChat: Replacing track for ${remoteUserId}`);
                    await audioSender.replaceTrack(audioTrack);
                } else if (audioSender && !audioSender.track) {
                    console.log(`VoiceChat: Replacing empty sender for ${remoteUserId}`);
                    await audioSender.replaceTrack(audioTrack);
                    await this.renegotiate(remoteUserId, pc);
                } else {
                    console.log(`VoiceChat: Adding new track for ${remoteUserId}`);
                    pc.addTrack(audioTrack, this.localStream);
                    await this.renegotiate(remoteUserId, pc);
                }
            }
        }
    }
    
    async renegotiate(remoteUserId, pc) {
        try {
            console.log(`VoiceChat: Renegotiating with ${remoteUserId}`);
            const offer = await pc.createOffer({ offerToReceiveAudio: true });
            await pc.setLocalDescription(offer);
            await this.sendOffer(remoteUserId, offer);
        } catch (error) {
            console.error(`VoiceChat: Renegotiation error with ${remoteUserId}`, error);
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
        } catch (error) {
            console.error('VoiceChat: Speaking detection error', error);
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
        const pc = this.peerConnections[remoteUserId];
        if (pc) {
            pc.close();
            delete this.peerConnections[remoteUserId];
        }
        
        const audioEl = this.remoteAudioElements[remoteUserId];
        if (audioEl) {
            audioEl.srcObject = null;
            audioEl.remove();
            delete this.remoteAudioElements[remoteUserId];
        }
        
        console.log(`VoiceChat: Closed connection with ${remoteUserId}`);
    }
    
    async destroy() {
        console.log('VoiceChat: Destroying...');
        
        this.unsubscribers.forEach(unsub => unsub());
        this.unsubscribers = [];
        
        for (const remoteUserId of Object.keys(this.peerConnections)) {
            this.closePeerConnection(remoteUserId);
        }
        
        await this.disableMicrophone();
        
        try {
            const voiceRef = this.db.collection('voiceSessions').doc(this.sessionId);
            await voiceRef.collection('participants').doc(String(this.localUserId)).delete();
        } catch (error) {
            console.warn('VoiceChat: Error cleaning up participant', error);
        }
        
        this.isConnected = false;
        console.log('VoiceChat: Destroyed');
    }
}

if (typeof window !== 'undefined') {
    window.VoiceChat = VoiceChat;
}
