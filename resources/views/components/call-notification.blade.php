@if(auth()->check() && auth()->user()->is_staff)
<div x-data="callNotification()" x-init="init()" class="fixed z-[200]">
    <!-- Incoming Call Modal -->
    <div x-show="incomingCall" x-transition class="fixed inset-0 z-[200] flex items-center justify-center bg-black/70">
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 text-center shadow-2xl max-w-sm w-full mx-4">
            <div class="w-24 h-24 mx-auto mb-4 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center animate-pulse">
                <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2" x-text="incomingCall?.caller_name"></h3>
            <p class="text-gray-500 dark:text-gray-400 mb-8">تماس ورودی...</p>
            <div class="flex gap-6 justify-center">
                <button @click="rejectCall()" class="w-16 h-16 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center transition transform hover:scale-105">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
                <button @click="answerCall()" class="w-16 h-16 bg-green-500 hover:bg-green-600 text-white rounded-full flex items-center justify-center transition transform hover:scale-105 animate-bounce">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Active Call Modal -->
    <div x-show="activeCall" x-transition class="fixed inset-0 z-[200] flex items-center justify-center bg-gradient-to-br from-brand-600 to-brand-800">
        <div class="text-center text-white">
            <div class="w-32 h-32 mx-auto mb-6 rounded-full bg-white/20 flex items-center justify-center text-5xl font-bold" x-text="activeCall?.remote_name?.charAt(0)"></div>
            <h3 class="text-3xl font-bold mb-2" x-text="activeCall?.remote_name"></h3>
            <p class="text-xl opacity-80 mb-8" x-text="callDuration"></p>
            <div class="flex gap-6 justify-center">
                <button @click="toggleMute()" :class="isMuted ? 'bg-red-500' : 'bg-white/20'" class="w-14 h-14 rounded-full flex items-center justify-center transition hover:opacity-80">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path x-show="!isMuted" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                        <path x-show="isMuted" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/>
                    </svg>
                </button>
                <button @click="endCall()" class="w-14 h-14 bg-red-500 hover:bg-red-600 rounded-full flex items-center justify-center transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M5 3a2 2 0 00-2 2v1c0 8.284 6.716 15 15 15h1a2 2 0 002-2v-3.28a1 1 0 00-.684-.948l-4.493-1.498a1 1 0 00-1.21.502l-1.13 2.257a11.042 11.042 0 01-5.516-5.517l2.257-1.128a1 1 0 00.502-1.21L9.228 3.683A1 1 0 008.279 3H5z"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Unread Messages Badge (Floating) -->
    <a href="{{ route('admin.messenger') }}" x-show="unreadCount > 0" class="fixed bottom-6 left-6 bg-brand-500 hover:bg-brand-600 text-white rounded-full p-4 shadow-lg flex items-center gap-2 transition">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        <span class="bg-red-500 text-xs px-2 py-0.5 rounded-full" x-text="unreadCount"></span>
    </a>

    <!-- Audio Elements -->
    <audio x-ref="ringtone" loop>
        <source src="data:audio/wav;base64,UklGRl9vT19teleQ0FAQEBXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQ==" type="audio/wav">
    </audio>
    <audio x-ref="localAudio" muted></audio>
    <audio x-ref="remoteAudio" autoplay></audio>
</div>

<script>
function callNotification() {
    return {
        incomingCall: null,
        activeCall: null,
        callDuration: '00:00',
        callTimer: null,
        callSignalTimer: null,
        isMuted: false,
        unreadCount: 0,
        peerConnection: null,
        localStream: null,
        lastSignalSeq: -1,
        pendingIceCandidates: [],

        async init() {
            if (window.location.pathname.includes('/messenger')) return;

            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }

            setInterval(() => {
                this.checkIncomingCalls();
                this.checkUnreadMessages();
            }, 2000);
        },

        async checkIncomingCalls() {
            if (this.incomingCall || this.activeCall) return;
            try {
                const response = await fetch('/admin/chat/calls/incoming');
                const data = await response.json();
                if (data.has_call && data.call) {
                    this.incomingCall = data.call;
                    this.$refs.ringtone.play().catch(() => {});
                    if (Notification.permission === 'granted') {
                        new Notification('تماس ورودی', {
                            body: `${data.call.caller_name} در حال تماس است...`,
                            icon: '/favicon.ico',
                            tag: 'incoming-call',
                            requireInteraction: true
                        });
                    }
                }
            } catch (e) {}
        },

        async checkUnreadMessages() {
            try {
                const response = await fetch('/admin/chat/unread-count');
                const data = await response.json();
                const newCount = data.unread_count || 0;
                if (newCount > this.unreadCount && this.unreadCount > 0) {
                    if (Notification.permission === 'granted') {
                        new Notification('پیام جدید', {
                            body: `شما ${newCount} پیام خوانده نشده دارید`,
                            icon: '/favicon.ico'
                        });
                    }
                }
                this.unreadCount = newCount;
            } catch (e) {}
        },

        async answerCall() {
            if (!this.incomingCall) return;
            const callId = this.incomingCall.id;
            const callerId = this.incomingCall.caller_id;
            const callerName = this.incomingCall.caller_name;

            this.incomingCall = null;
            this.$refs.ringtone.pause();

            try {
                this.localStream = await navigator.mediaDevices.getUserMedia({
                    audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
                    video: false
                });
                this.$refs.localAudio.srcObject = this.localStream;
            } catch (e) {
                alert('خطا در دسترسی به میکروفون. لطفا از HTTPS استفاده کنید.');
                return;
            }

            try {
                const response = await fetch(`/admin/chat/calls/${callId}/answer`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();
                if (!data.call) return;

                this.activeCall = { id: callId, remote_name: callerName };
                this.lastSignalSeq = -1;
                this.pendingIceCandidates = [];

                this.peerConnection = new RTCPeerConnection({
                    iceServers: [
                        { urls: 'stun:stun.l.google.com:19302' },
                        { urls: 'stun:stun1.l.google.com:19302' }
                    ]
                });
                this.localStream.getTracks().forEach(track => {
                    this.peerConnection.addTrack(track, this.localStream);
                });
                this.peerConnection.ontrack = (event) => {
                    this.$refs.remoteAudio.srcObject = event.streams[0];
                };
                this.peerConnection.onicecandidate = (event) => {
                    if (event.candidate) this.sendSignal('ice-candidate', event.candidate);
                };
                this.peerConnection.onconnectionstatechange = () => {
                    const state = this.peerConnection?.connectionState;
                    if (state === 'failed' || state === 'disconnected') this.endCall();
                };

                this.callSignalTimer = setInterval(() => this.pollCallSignals(), 600);
                this.startCallTimer();
            } catch (e) {
                this.cleanupCall();
            }
        },

        async rejectCall() {
            if (!this.incomingCall) return;
            try {
                await fetch(`/admin/chat/calls/${this.incomingCall.id}/reject`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
            } catch (e) {}
            this.incomingCall = null;
            this.$refs.ringtone.pause();
        },

        async endCall() {
            const callId = this.activeCall?.id;
            if (callId) {
                try {
                    await fetch(`/admin/chat/calls/${callId}/end`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });
                } catch (e) {}
            }
            this.cleanupCall();
        },

        cleanupCall() {
            if (this.peerConnection) { this.peerConnection.close(); this.peerConnection = null; }
            if (this.localStream) { this.localStream.getTracks().forEach(t => t.stop()); this.localStream = null; }
            if (this.callTimer) { clearInterval(this.callTimer); this.callTimer = null; }
            if (this.callSignalTimer) { clearInterval(this.callSignalTimer); this.callSignalTimer = null; }
            this.activeCall = null;
            this.callDuration = '00:00';
            this.isMuted = false;
            this.lastSignalSeq = -1;
            this.pendingIceCandidates = [];
        },

        startCallTimer() {
            let seconds = 0;
            this.callTimer = setInterval(() => {
                seconds++;
                const m = Math.floor(seconds / 60).toString().padStart(2, '0');
                const s = (seconds % 60).toString().padStart(2, '0');
                this.callDuration = `${m}:${s}`;
            }, 1000);
        },

        toggleMute() {
            this.isMuted = !this.isMuted;
            if (this.localStream) {
                this.localStream.getAudioTracks().forEach(t => { t.enabled = !this.isMuted; });
            }
        },

        async pollCallSignals() {
            const callId = this.activeCall?.id;
            if (!callId) return;
            try {
                const response = await fetch(`/admin/chat/signals/poll?call_id=${callId}&last_seq=${this.lastSignalSeq}`);
                const data = await response.json();
                for (const signal of (data.signals || [])) {
                    await this.handleSignal(signal);
                    if (signal.seq > this.lastSignalSeq) this.lastSignalSeq = signal.seq;
                }
                if (data.status === 'ended' || data.status === 'rejected') {
                    this.cleanupCall();
                }
            } catch (e) {}
        },

        async handleSignal(signal) {
            if (!this.peerConnection) return;
            try {
                if (signal.type === 'offer') {
                    await this.peerConnection.setRemoteDescription(new RTCSessionDescription(signal.data));
                    for (const c of this.pendingIceCandidates) {
                        try { await this.peerConnection.addIceCandidate(new RTCIceCandidate(c)); } catch (e) {}
                    }
                    this.pendingIceCandidates = [];
                    const answer = await this.peerConnection.createAnswer();
                    await this.peerConnection.setLocalDescription(answer);
                    await this.sendSignal('answer', answer);
                } else if (signal.type === 'answer') {
                    await this.peerConnection.setRemoteDescription(new RTCSessionDescription(signal.data));
                    for (const c of this.pendingIceCandidates) {
                        try { await this.peerConnection.addIceCandidate(new RTCIceCandidate(c)); } catch (e) {}
                    }
                    this.pendingIceCandidates = [];
                } else if (signal.type === 'ice-candidate') {
                    if (this.peerConnection.remoteDescription) {
                        await this.peerConnection.addIceCandidate(new RTCIceCandidate(signal.data));
                    } else {
                        this.pendingIceCandidates.push(signal.data);
                    }
                }
            } catch (e) {
                console.error('Signal error:', signal.type, e);
            }
        },

        async sendSignal(type, data) {
            const callId = this.activeCall?.id;
            if (!callId) return;
            try {
                await fetch('/admin/chat/signal', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ call_id: callId, type, data })
                });
            } catch (e) {}
        }
    }
}
</script>
@endif
