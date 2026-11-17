// Inject Hugo Preview button into Nextcloud Files app sidebar
document.addEventListener('DOMContentLoaded', () => {
    let attempts = 0;

    const checkAndInject = () => {
        attempts++;
        // Nextcloud Vue apps usually use .app-navigation-list or #app-navigation > ul
        const navList = document.querySelector('.app-navigation-list') || document.querySelector('#app-navigation > ul');

        if (!navList) {
            if (attempts < 20) { // Try for 10 seconds
                setTimeout(checkAndInject, 500)
            } else {
                console.warn('[Hugo Preview] Could not find Nextcloud app navigation list after 20 attempts.');
            }
            return
        }


        // Prevent duplicate injection
        if (document.querySelector('#hugo-preview-nav-item')) return

        const li = document.createElement('li')
        li.id = 'hugo-preview-nav-item'
        li.className = 'nav-icon-files' // Use generic NC class for alignment

        const a = document.createElement('a')
        a.href = OC.generateUrl('/apps/hugopreview_nc_app/')

        // SVG Icon (eye/preview)
        const iconSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg')
        iconSvg.setAttribute('width', '20')
        iconSvg.setAttribute('height', '20')
        iconSvg.setAttribute('viewBox', '0 0 24 24')
        iconSvg.classList.add('app-icon')
        // Inline styles for safe alignment with Nextcloud icons
        iconSvg.style.width = '20px'
        iconSvg.style.height = '20px'
        iconSvg.style.opacity = '0.7'
        iconSvg.style.fill = 'none'
        iconSvg.style.stroke = 'currentColor'
        iconSvg.style.strokeWidth = '2'
        iconSvg.style.strokeLinecap = 'round'
        iconSvg.style.strokeLinejoin = 'round'
        iconSvg.style.marginRight = '8px'
        iconSvg.style.display = 'inline-block'
        iconSvg.style.verticalAlign = 'middle'

        // Eye path
        const path1 = document.createElementNS('http://www.w3.org/2000/svg', 'path')
        path1.setAttribute('d', 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z')
        const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle')
        circle.setAttribute('cx', '12')
        circle.setAttribute('cy', '12')
        circle.setAttribute('r', '3')

        iconSvg.appendChild(path1)
        iconSvg.appendChild(circle)

        const span = document.createElement('span')
        span.textContent = 'Hugo Preview'
        span.style.verticalAlign = 'middle'
        span.style.fontWeight = '500'

        a.appendChild(iconSvg)
        a.appendChild(span)
        a.style.display = 'flex'
        a.style.alignItems = 'center'
        a.style.padding = '10px 14px'
        a.style.color = 'var(--color-main-text)'

        a.addEventListener('mouseenter', () => { a.style.opacity = '1' })
        a.addEventListener('mouseleave', () => { a.style.opacity = '0.8' })

        li.appendChild(a)
        navList.appendChild(li)
    }

    // Start checking for Sidebar injection
    checkAndInject()

    // Auto-Sync Toggle for Nextcloud Text Editor
    function initAutoSync() {
        let syncEnabled = false;
        let typingTimer;

        // Create floating UI
        const container = document.createElement('div');
        container.id = 'hugo-auto-sync-toggle';
        container.style = 'position:fixed; bottom:30px; right:30px; z-index:9999; background:var(--color-main-background); border:1px solid var(--color-border); border-radius:30px; padding:10px 18px; display:none; align-items:center; gap:8px; box-shadow:0 4px 12px rgba(0,0,0,0.15); font-family:var(--font-face); cursor:pointer; transition:all 0.3s;';

        container.innerHTML = `
            <input type="checkbox" id="hugo-sync-checkbox" style="cursor:pointer; width:16px; height:16px; margin:0;" />
            <label for="hugo-sync-checkbox" style="cursor:pointer; font-weight:600; color:var(--color-main-text); margin:0; user-select:none;">Live Preview Sync</label>
            <span id="hugo-sync-status" style="font-size:12px; color:var(--color-text-maxcontrast); margin-left:8px; font-weight:bold;">Off</span>
        `;

        document.body.appendChild(container);

        // Toggle logic
        const checkbox = container.querySelector('#hugo-sync-checkbox');
        const status = container.querySelector('#hugo-sync-status');

        checkbox.addEventListener('change', (e) => {
            syncEnabled = e.target.checked;
            status.textContent = syncEnabled ? 'Watching...' : 'Off';
            status.style.color = syncEnabled ? '#019a5a' : 'var(--color-text-maxcontrast)';
            if (syncEnabled) {
                container.style.borderColor = '#019a5a';
                container.style.boxShadow = '0 4px 12px rgba(1,154,90,0.2)';
            } else {
                container.style.borderColor = 'var(--color-border)';
                container.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
            }
        });

        // Observe DOM for editor to show/hide the toggle
        setInterval(() => {
            const hasEditor = document.querySelector('.text-app') || document.querySelector('.ProseMirror');
            if (hasEditor) {
                container.style.display = 'flex';

                // Hack: Fix Nextcloud Text app's Formatting Help popup for Blockquote
                // Nextcloud natively lists it as "Ctrl + >" but the real shortcut is "Ctrl + Shift + B"
                document.querySelectorAll('kbd').forEach(kbd => {
                    if (kbd.textContent.trim() === '>') {
                        const prev = kbd.previousElementSibling;
                        if (prev && prev.tagName === 'KBD' && (prev.textContent.trim() === 'Ctrl' || prev.textContent.trim() === 'Cmd')) {
                            kbd.textContent = 'Shift';
                            const plus = document.createTextNode(' + ');
                            const bKbd = document.createElement('kbd');
                            bKbd.textContent = 'B';
                            kbd.parentNode.insertBefore(plus, kbd.nextSibling);
                            kbd.parentNode.insertBefore(bKbd, plus.nextSibling);
                        }
                    }
                });
            } else {
                container.style.display = 'none';
            }
        }, 1000);

        let isWaitingForSave = false;

        // Intercept XMLHttpRequest to detect when Nextcloud Text finishes saving to disk
        const origOpen = XMLHttpRequest.prototype.open;
        const origSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.open = function(method, url) {
            this._ncUrl = url;
            return origOpen.apply(this, arguments);
        };
        XMLHttpRequest.prototype.send = function() {
            this.addEventListener('load', () => {
                if (this._ncUrl && this._ncUrl.includes('/apps/text/session/save')) {
                    if (syncEnabled && isWaitingForSave) {
                        isWaitingForSave = false;
                        triggerHugoBuild();
                    }
                }
            });
            return origSend.apply(this, arguments);
        };

        function triggerHugoBuild() {
            status.textContent = 'Building...';
            status.style.color = 'var(--color-primary)';

            fetch(OC.generateUrl('/apps/hugopreview_nc_app/api/build/all'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'requesttoken': OC.requestToken }
            }).then(res => res.json()).then(data => {
                if (data.status === 'success' || data.status === 'error') {
                    if (data.status === 'success') {
                        status.textContent = 'Synced \u2713';
                        status.style.color = '#019a5a';
                        localStorage.setItem('hugo_auto_sync_event', JSON.stringify({ timestamp: Date.now(), success: true }));
                    } else {
                        status.textContent = 'Syntax error';
                        status.style.color = '#e59900';
                        localStorage.setItem('hugo_auto_sync_event', JSON.stringify({ timestamp: Date.now(), success: false, error: data.error }));
                    }
                    setTimeout(() => { if (status.textContent.includes('Synced')) status.textContent = 'Watching...'; }, 3000);
                    // Broadcast legacy event for backwards compatibility
                    localStorage.setItem('hugo_auto_sync_reload', Date.now().toString());
                } else {
                    status.textContent = 'Error';
                    status.style.color = '#d2322d';
                }
            }).catch(() => {
                status.textContent = 'Error';
                status.style.color = '#d2322d';
            });
        }

        // Watch for text changes using MutationObserver on ProseMirror
        const observer = new MutationObserver(() => {
            if (!syncEnabled) return;

            status.textContent = 'Typing...';
            status.style.color = 'var(--color-primary)';
            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => {
                status.textContent = 'Saving...';
                isWaitingForSave = true;

                // Force Nextcloud Text to save to disk by simulating Ctrl+S on the editor
                const pm = document.querySelector('.ProseMirror');
                if (pm) {
                    pm.dispatchEvent(new KeyboardEvent('keydown', {
                        key: 's',
                        code: 'KeyS',
                        ctrlKey: true,
                        metaKey: true,
                        bubbles: true,
                        cancelable: true
                    }));
                }

                // Fallback: if the save intercept doesn't catch anything within 2 seconds, force build anyway
                setTimeout(() => {
                    if (isWaitingForSave) {
                        isWaitingForSave = false;
                        triggerHugoBuild();
                    }
                }, 1500);

            }, 400); // Wait 400ms (instead of 1000ms) after typing stops to force save
        });

        let attached = false;
        setInterval(() => {
            const pm = document.querySelector('.ProseMirror');
            if (pm && !attached) {
                observer.observe(pm, { characterData: true, childList: true, subtree: true });
                attached = true;
            } else if (!pm && attached) {
                observer.disconnect();
                attached = false;
            }
        }, 1000);
    }

    initAutoSync();
})
