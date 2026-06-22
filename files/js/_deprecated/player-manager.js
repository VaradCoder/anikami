(function () {
    'use strict';

    function isNonEmptyString(value) {
        return typeof value === 'string' && value.trim() !== '';
    }

    function normalizeType(server) {
        const type = String(server && server.type ? server.type : '').toLowerCase();
        if (type) {
            return type;
        }

        const url = String(server && server.url ? server.url : server && server.playbackUrl ? server.playbackUrl : '');
        if (url.toLowerCase().includes('.m3u8')) {
            return 'hls';
        }
        if (url.toLowerCase().includes('.mp4')) {
            return 'mp4';
        }
        return 'iframe';
    }

    function canUseNativeHls(videoEl) {
        return !!(videoEl && typeof videoEl.canPlayType === 'function' && videoEl.canPlayType('application/vnd.apple.mpegurl'));
    }

    class PlayerManager {
        constructor(options) {
            this.options = options || {};
            this.rootEl = document.getElementById(this.options.rootId || 'anime-player');
            this.videoEl = document.getElementById(this.options.videoId || 'anime-player-video');
            this.iframeEl = document.getElementById(this.options.iframeId || 'anime-player-iframe');
            this.loadingEl = document.getElementById(this.options.loadingId || 'embed-loading');
            this.switchMsgEl = document.getElementById(this.options.switchMessageId || 'server-switch-message');
            this.sourceGuideEl = document.getElementById(this.options.sourceGuideId || 'source-guide');

            this.servers = [];
            this.hls = null;
            this.plyr = null;
            this.activeTarget = null;
            this.failoverTimer = null;

            this.state = {
                serverIndex: -1,
                status: 'idle',
                type: '',
                url: '',
                retries: 0,
                autoNextEnabled: this.options.autoNextEnabled !== false,
                nextUrl: isNonEmptyString(this.options.nextUrl) ? this.options.nextUrl : '',
                currentTime: 0,
                duration: 0,
            };

            this.failover = Object.assign({
                enabled: true,
                timeoutMs: 12000,
                maxRetriesPerServer: 1,
            }, this.options.failover || {});

            this.onVideoEnded = this.onVideoEnded.bind(this);
            this.onVideoTimeUpdate = this.onVideoTimeUpdate.bind(this);
            this.onVideoLoadedMetadata = this.onVideoLoadedMetadata.bind(this);
            this.onIframeLoad = this.onIframeLoad.bind(this);

            this.bindStaticEvents();
            this.ensurePlyr();
            this.renderGuide();
        }

        bindStaticEvents() {
            if (this.videoEl) {
                this.videoEl.addEventListener('ended', this.onVideoEnded);
                this.videoEl.addEventListener('timeupdate', this.onVideoTimeUpdate);
                this.videoEl.addEventListener('loadedmetadata', this.onVideoLoadedMetadata);
                this.videoEl.addEventListener('error', () => {
                    this.handlePlaybackFailure('Video playback error detected.');
                });
            }

            if (this.iframeEl) {
                this.iframeEl.addEventListener('load', this.onIframeLoad);
            }
        }

        ensurePlyr() {
            if (!this.videoEl || !window.Plyr || this.plyr) {
                return;
            }

            try {
                this.plyr = new window.Plyr(this.videoEl, {
                    controls: [
                        'play-large',
                        'play',
                        'progress',
                        'current-time',
                        'mute',
                        'volume',
                        'captions',
                        'settings',
                        'fullscreen'
                    ],
                    settings: ['quality', 'captions', 'speed'],
                    captions: {
                        active: true,
                        update: true
                    }
                });
            } catch (error) {
                this.plyr = null;
            }
        }

        destroyPlayback() {
            if (this.failoverTimer) {
                clearTimeout(this.failoverTimer);
                this.failoverTimer = null;
            }

            if (this.hls) {
                try {
                    this.hls.destroy();
                } catch (error) {}
                this.hls = null;
            }

            if (this.videoEl) {
                try {
                    this.videoEl.pause();
                } catch (error) {}
                this.videoEl.removeAttribute('src');
                this.videoEl.load();
                while (this.videoEl.firstChild) {
                    this.videoEl.removeChild(this.videoEl.firstChild);
                }
            }

            if (this.iframeEl) {
                this.iframeEl.removeAttribute('src');
            }

            this.state.currentTime = 0;
            this.state.duration = 0;
        }

        destroy() {
            this.destroyPlayback();

            if (this.videoEl) {
                this.videoEl.removeEventListener('ended', this.onVideoEnded);
                this.videoEl.removeEventListener('timeupdate', this.onVideoTimeUpdate);
                this.videoEl.removeEventListener('loadedmetadata', this.onVideoLoadedMetadata);
            }

            if (this.iframeEl) {
                this.iframeEl.removeEventListener('load', this.onIframeLoad);
            }

            if (this.plyr) {
                try {
                    this.plyr.destroy();
                } catch (error) {}
                this.plyr = null;
            }
        }

        setServers(servers) {
            this.servers = Array.isArray(servers) ? servers.map((server, index) => {
                const playbackUrl = String(server && (server.playbackUrl || server.url) ? (server.playbackUrl || server.url) : '');
                return Object.assign({}, server, {
                    index: index,
                    playbackUrl: playbackUrl,
                    url: playbackUrl,
                    type: normalizeType(server),
                    subtitles: Array.isArray(server && server.subtitles) ? server.subtitles : [],
                });
            }).filter((server) => isNonEmptyString(server.playbackUrl)) : [];

            this.renderGuide();
        }

        setAutoNext(enabled, nextUrl) {
            this.state.autoNextEnabled = !!enabled;
            if (isNonEmptyString(nextUrl)) {
                this.state.nextUrl = nextUrl;
            }
        }

        showLoading(show) {
            if (this.loadingEl) {
                this.loadingEl.style.display = show ? 'block' : 'none';
            }
        }

        showMessage(message, visible) {
            if (!this.switchMsgEl) {
                return;
            }

            if (isNonEmptyString(message)) {
                this.switchMsgEl.textContent = message;
            }
            this.switchMsgEl.style.display = visible ? 'block' : 'none';
        }

        setPlayerMode(mode) {
            this.activeTarget = mode;
            if (this.rootEl) {
                this.rootEl.setAttribute('data-player-mode', mode);
            }
            if (this.videoEl) {
                this.videoEl.hidden = mode !== 'video';
            }
            if (this.iframeEl) {
                this.iframeEl.hidden = mode !== 'iframe';
            }
        }

        updateActiveButton(index) {
            const buttons = Array.from(document.querySelectorAll('.btn-server'));
            buttons.forEach((button) => {
                const buttonIndex = parseInt(button.getAttribute('data-server-index') || '-1', 10);
                button.classList.toggle('active', buttonIndex === index);
            });
        }

        renderGuide() {
            if (!this.sourceGuideEl) {
                return;
            }

            if (this.state.serverIndex < 0 || !this.servers[this.state.serverIndex]) {
                this.sourceGuideEl.innerHTML = '';
                return;
            }

            const server = this.servers[this.state.serverIndex];
            const parts = [
                '<div class="pm-guide">',
                '<span class="pm-pill">Mode: ' + this.escapeHtml(this.state.type.toUpperCase()) + '</span>'
            ];

            if (isNonEmptyString(server.name)) {
                parts.push('<span class="pm-pill">Server: ' + this.escapeHtml(server.name) + '</span>');
            }
            if (isNonEmptyString(server.quality)) {
                parts.push('<span class="pm-pill">Quality: ' + this.escapeHtml(server.quality) + '</span>');
            }
            if (isNonEmptyString(server.lang)) {
                parts.push('<span class="pm-pill">Audio: ' + this.escapeHtml(server.lang) + '</span>');
            }
            if (Array.isArray(server.subtitles) && server.subtitles.length > 0) {
                parts.push('<span class="pm-pill">Subs: ' + server.subtitles.length + '</span>');
            }
            parts.push('</div>');

            this.sourceGuideEl.innerHTML = parts.join('');
        }

        escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        bindServerButtons(containerSelector) {
            const container = document.querySelector(containerSelector);
            if (!container) {
                return;
            }

            const buttons = Array.from(container.querySelectorAll('.btn-server'));
            buttons.forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    const index = parseInt(button.getAttribute('data-server-index') || '-1', 10);
                    if (index >= 0) {
                        this.switchServer(index, { manual: true });
                    }
                });
            });
        }

        startFailoverTimer() {
            if (this.state.type === 'iframe') {
                return;
            }

            if (this.state.type !== 'hls' && this.state.type !== 'mp4') {
                return;
            }

            if (!this.failover.enabled) {
                return;
            }

            if (this.failoverTimer) {
                clearTimeout(this.failoverTimer);
            }

            this.failoverTimer = window.setTimeout(() => {
                this.handlePlaybackFailure('Server timed out. Trying the next source...');
            }, this.failover.timeoutMs);
        }

        clearFailoverTimer() {
            if (this.failoverTimer) {
                clearTimeout(this.failoverTimer);
                this.failoverTimer = null;
            }
        }

        loadInitial() {
            if (!this.servers.length) {
                this.showMessage('Stream not available for this episode. Try another server or episode.', true);
                return;
            }

            const storedIndex = parseInt(window.localStorage.getItem('currentServerIndex') || '-1', 10);
            const firstIndex = storedIndex >= 0 && storedIndex < this.servers.length ? storedIndex : 0;
            this.switchServer(firstIndex, { manual: false });
        }

        switchServer(index, options) {
            const settings = Object.assign({ manual: false }, options || {});
            const server = this.servers[index];
            if (!server) {
                return Promise.resolve(false);
            }

            this.clearFailoverTimer();
            this.state.serverIndex = index;
            this.state.type = normalizeType(server);
            this.state.url = server.playbackUrl;
            this.state.status = 'loading';
            this.state.retries = 0;

            this.updateActiveButton(index);
            this.renderGuide();
            this.showLoading(true);
            this.showMessage(settings.manual ? 'Switching server...' : 'Loading stream...', true);

            try {
                window.localStorage.setItem('currentServerIndex', String(index));
            } catch (error) {}

            return this.loadServer(server);
        }

        loadServer(server) {
            const type = normalizeType(server);
            if (type === 'hls') {
                return this.loadHls(server);
            }
            if (type === 'mp4') {
                return this.loadDirectVideo(server);
            }
            return this.loadIframe(server);
        }

        loadDirectVideo(server) {
            this.destroyPlayback();
            this.ensurePlyr();
            this.setPlayerMode('video');

            if (!this.videoEl) {
                return this.loadIframe(server);
            }

            try {
                this.attachSubtitles(server.subtitles);
                this.videoEl.src = server.playbackUrl;
                this.videoEl.load();
                const playPromise = this.videoEl.play();
                if (playPromise && typeof playPromise.catch === 'function') {
                    playPromise.catch(function () {});
                }
            } catch (error) {
                return this.loadIframe(server);
            }

            this.state.status = 'playing';
            this.startFailoverTimer();
            this.showLoading(false);
            this.showMessage('', false);
            return Promise.resolve(true);
        }

        loadHls(server) {
            this.destroyPlayback();
            this.ensurePlyr();
            this.setPlayerMode('video');

            if (!this.videoEl) {
                return this.loadIframe(server);
            }

            this.attachSubtitles(server.subtitles);

            if (window.Hls && window.Hls.isSupported && window.Hls.isSupported()) {
                try {
                    this.hls = new window.Hls(Object.assign({
                        enableWorker: true,
                        lowLatencyMode: false,
                    }, this.options.hlsConfig || {}));

                    this.hls.attachMedia(this.videoEl);
                    this.hls.on(window.Hls.Events.MEDIA_ATTACHED, () => {
                        if (this.hls) {
                            this.hls.loadSource(server.playbackUrl);
                        }
                    });

                    this.hls.on(window.Hls.Events.MANIFEST_PARSED, () => {
                        this.syncQualityOptions();
                        this.state.status = 'playing';
                        this.showLoading(false);
                        this.showMessage('', false);
                        this.startFailoverTimer();
                        const playPromise = this.videoEl.play();
                        if (playPromise && typeof playPromise.catch === 'function') {
                            playPromise.catch(function () {});
                        }
                    });

                    this.hls.on(window.Hls.Events.ERROR, (event, data) => {
                        if (!data) {
                            return;
                        }

                        if (data.fatal) {
                            this.handlePlaybackFailure('HLS playback failed. Falling back...');
                        }
                    });

                    return Promise.resolve(true);
                } catch (error) {
                    return this.loadIframe(server);
                }
            }

            if (canUseNativeHls(this.videoEl)) {
                this.videoEl.src = server.playbackUrl;
                this.videoEl.load();
                this.state.status = 'playing';
                this.showLoading(false);
                this.showMessage('', false);
                this.startFailoverTimer();
                const playPromise = this.videoEl.play();
                if (playPromise && typeof playPromise.catch === 'function') {
                    playPromise.catch(function () {});
                }
                return Promise.resolve(true);
            }

            return this.loadIframe(server);
        }

        syncQualityOptions() {
            if (!this.hls || !this.plyr || !Array.isArray(this.hls.levels) || !this.hls.levels.length) {
                return;
            }

            const levels = this.hls.levels;
            const qualities = levels
                .map((level) => level.height)
                .filter((height, index, arr) => typeof height === 'number' && arr.indexOf(height) === index)
                .sort((a, b) => b - a);

            if (!qualities.length) {
                return;
            }

            this.plyr.options.quality = {
                default: qualities[0],
                options: qualities,
                forced: true,
                onChange: (newQuality) => {
                    const levelIndex = levels.findIndex((level) => level.height === newQuality);
                    if (levelIndex >= 0 && this.hls) {
                        this.hls.currentLevel = levelIndex;
                    }
                }
            };
        }

        attachSubtitles(subtitles) {
            if (!this.videoEl) {
                return;
            }

            while (this.videoEl.querySelector('track')) {
                this.videoEl.removeChild(this.videoEl.querySelector('track'));
            }

            if (!Array.isArray(subtitles)) {
                return;
            }

            subtitles.forEach((subtitle, index) => {
                if (!subtitle || !isNonEmptyString(subtitle.url || subtitle.src)) {
                    return;
                }

                const track = document.createElement('track');
                track.kind = 'subtitles';
                track.label = String(subtitle.label || subtitle.lang || ('Subtitle ' + (index + 1)));
                track.srclang = String(subtitle.lang || 'en').toLowerCase();
                track.src = String(subtitle.url || subtitle.src);
                if (index === 0) {
                    track.default = true;
                }
                this.videoEl.appendChild(track);
            });
        }

        loadIframe(server) {
            this.destroyPlayback();
            this.setPlayerMode('iframe');

            if (!this.iframeEl) {
                this.showLoading(false);
                this.showMessage('No fallback player is available.', true);
                return Promise.resolve(false);
            }

            this.iframeEl.src = server.playbackUrl;
            this.state.status = 'playing';
            this.startFailoverTimer();

            window.setTimeout(() => {
                this.showLoading(false);
                this.showMessage('', false);
            }, 1400);

            return Promise.resolve(true);
        }

        handlePlaybackFailure(message) {
            this.clearFailoverTimer();

            const currentIndex = this.state.serverIndex;
            if (currentIndex < 0) {
                this.showLoading(false);
                this.showMessage(message || 'Playback failed.', true);
                return;
            }

            if (this.state.retries < this.failover.maxRetriesPerServer) {
                this.state.retries += 1;
                this.showMessage(message || 'Retrying current server...', true);
                this.loadServer(this.servers[currentIndex]);
                return;
            }

            const nextIndex = currentIndex + 1;
            if (nextIndex < this.servers.length) {
                this.showMessage(message || 'Switching to backup server...', true);
                this.switchServer(nextIndex, { manual: false });
                return;
            }

            this.state.status = 'failed';
            this.showLoading(false);
            this.showMessage('All servers failed. Please try another server or episode.', true);
        }

        onVideoEnded() {
            this.clearFailoverTimer();
            if (this.state.autoNextEnabled && isNonEmptyString(this.state.nextUrl)) {
                window.location.href = this.state.nextUrl;
            }
        }

        onVideoTimeUpdate() {
            if (!this.videoEl) {
                return;
            }

            this.state.currentTime = Number(this.videoEl.currentTime || 0);
            this.state.duration = Number(this.videoEl.duration || 0);
        }

        onVideoLoadedMetadata() {
            this.clearFailoverTimer();
            this.showLoading(false);
            this.showMessage('', false);
        }

        onIframeLoad() {
            if (this.activeTarget !== 'iframe') {
                return;
            }

            this.clearFailoverTimer();
            this.showLoading(false);
            this.showMessage('', false);
        }

        getPlaybackState() {
            return Object.assign({}, this.state);
        }
    }

    window.PlayerManager = PlayerManager;
})();
