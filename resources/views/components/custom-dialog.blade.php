{{-- Custom Dialog Modal - Replaces browser confirm() and alert() --}}
<div id="customDialogModal" class="custom-dialog-backdrop" style="display: none;">
    <div class="custom-dialog-content">
        <h3 id="customDialogTitle">{{ __('Confirmation') }}</h3>
        <p id="customDialogText"></p>
        <div class="custom-dialog-buttons" id="customDialogButtons">
            <button id="customDialogCancel" class="custom-dialog-btn custom-dialog-btn-secondary">{{ __('Annuler') }}</button>
            <button id="customDialogOk" class="custom-dialog-btn custom-dialog-btn-primary">{{ __('OK') }}</button>
        </div>
    </div>
</div>

<style>
.custom-dialog-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    backdrop-filter: blur(3px);
}

.custom-dialog-content {
    background: white;
    border-radius: 16px;
    padding: 25px 30px;
    max-width: 400px;
    width: 90%;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    text-align: center;
    animation: dialogSlideIn 0.2s ease-out;
}

@keyframes dialogSlideIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.custom-dialog-content h3 {
    margin: 0 0 15px 0;
    color: #1a1a1a;
    font-size: 1.2rem;
}

.custom-dialog-content p {
    margin: 0 0 25px 0;
    color: #444;
    font-size: 1rem;
    line-height: 1.5;
}

.custom-dialog-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.custom-dialog-btn {
    padding: 12px 28px;
    border: none;
    border-radius: 25px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.custom-dialog-btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.custom-dialog-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.custom-dialog-btn-secondary {
    background: #e0e0e0;
    color: #333;
}

.custom-dialog-btn-secondary:hover {
    background: #d0d0d0;
}

.custom-dialog-btn-danger {
    background: linear-gradient(135deg, #e53935 0%, #c62828 100%);
    color: white;
}

.custom-dialog-btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(229, 57, 53, 0.4);
}
</style>

<script>
window.customDialog = {
    show: function(options) {
        const modal = document.getElementById('customDialogModal');
        const titleEl = document.getElementById('customDialogTitle');
        const textEl = document.getElementById('customDialogText');
        const okBtn = document.getElementById('customDialogOk');
        const cancelBtn = document.getElementById('customDialogCancel');
        const buttonsDiv = document.getElementById('customDialogButtons');
        
        titleEl.textContent = options.title || '{{ __("Confirmation") }}';
        textEl.textContent = options.message || '';
        okBtn.textContent = options.okText || '{{ __("OK") }}';
        cancelBtn.textContent = options.cancelText || '{{ __("Annuler") }}';
        
        if (options.type === 'alert') {
            cancelBtn.style.display = 'none';
        } else {
            cancelBtn.style.display = 'inline-block';
        }
        
        if (options.danger) {
            okBtn.className = 'custom-dialog-btn custom-dialog-btn-danger';
        } else {
            okBtn.className = 'custom-dialog-btn custom-dialog-btn-primary';
        }
        
        modal.style.display = 'flex';
        
        const cleanup = () => {
            modal.style.display = 'none';
            okBtn.onclick = null;
            cancelBtn.onclick = null;
        };
        
        return new Promise((resolve) => {
            okBtn.onclick = () => {
                cleanup();
                resolve(true);
            };
            
            cancelBtn.onclick = () => {
                cleanup();
                resolve(false);
            };
            
            modal.onclick = (e) => {
                if (e.target === modal && options.type === 'alert') {
                    cleanup();
                    resolve(true);
                }
            };
        });
    },
    
    confirm: function(message, options = {}) {
        return this.show({
            type: 'confirm',
            title: options.title || '{{ __("Confirmation") }}',
            message: message,
            okText: options.okText || '{{ __("Confirmer") }}',
            cancelText: options.cancelText || '{{ __("Annuler") }}',
            danger: options.danger || false
        });
    },
    
    alert: function(message, options = {}) {
        return this.show({
            type: 'alert',
            title: options.title || '{{ __("Information") }}',
            message: message,
            okText: options.okText || '{{ __("OK") }}'
        });
    }
};
</script>
