/**
 * Feedbacker — embeddable testimonial widget.
 *
 * Loaded as `<script src="https://…/w/{slug}.js"></script>` (space-wide) or
 * `<script src="https://…/w/{slug}/{token}.js"></script>` (a saved widget);
 * the server prepends a `window.__FEEDBACKER__` config object.
 *
 * Four layouts share one bundle. Everything lives inside a shadow root, so
 * host page styles cannot leak in and widget styles cannot leak out.
 *
 * No HTML string is ever built from data: every value goes through
 * textContent or an attribute setter.
 */
(function () {
    'use strict';

    var CFG = window.__FEEDBACKER__;

    if (!CFG || !CFG.slug || !CFG.api || !CFG.endpoint) {
        console.warn('[feedbacker] widget config missing — check the script URL');
        return;
    }

    var O = CFG.options || {};
    var API = String(CFG.api).replace(/\/+$/, '');
    var LAYOUT = O.layout || 'masonry';
    var DARK = O.theme === 'dark';
    var ACCENT_FALLBACK = O.accent || '#4F46E5';

    // ── Small DOM helpers ──

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text != null) node.textContent = String(text);
        return node;
    }

    function initials(name) {
        var parts = String(name || '').trim().split(/\s+/).slice(0, 2);
        var out = '';

        for (var i = 0; i < parts.length; i++) {
            if (parts[i]) out += parts[i].charAt(0).toUpperCase();
        }

        return out || '?';
    }

    function stars(rating) {
        var full = Math.max(0, Math.min(5, Math.round(rating || 0)));
        return new Array(full + 1).join('★') + new Array(6 - full).join('☆');
    }

    // ── Video ──

    /**
     * Turn a stored URL into an embeddable source, or null when it is not a
     * provider we can frame.
     */
    function videoSource(url) {
        if (!url) return null;

        var match = String(url).match(
            /(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/,
        );
        if (match) {
            return {
                kind: 'iframe',
                src: 'https://www.youtube.com/embed/' + match[1] + '?autoplay=1',
            };
        }

        match = String(url).match(/vimeo\.com\/(?:video\/)?(\d+)/);
        if (match) {
            return {
                kind: 'iframe',
                src: 'https://player.vimeo.com/video/' + match[1] + '?autoplay=1',
            };
        }

        if (/^https?:\/\/.+\.(mp4|webm|ogg)(\?.*)?$/i.test(url)) {
            return { kind: 'video', src: url };
        }

        return null;
    }

    var lightbox = null;

    function closeLightbox() {
        if (!lightbox) return;
        lightbox.remove();
        lightbox = null;
    }

    function openVideo(root, url) {
        var source = videoSource(url);
        if (!source) {
            window.open(url, '_blank', 'noopener');
            return;
        }

        closeLightbox();

        lightbox = el('div', 'fb-lightbox');
        lightbox.setAttribute('role', 'dialog');
        lightbox.setAttribute('aria-modal', 'true');

        var stage = el('div', 'fb-lightbox-stage');
        var node;

        if (source.kind === 'iframe') {
            node = document.createElement('iframe');
            node.src = source.src;
            node.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture';
            node.allowFullscreen = true;
            node.setAttribute('title', 'Video testimonial');
        } else {
            node = document.createElement('video');
            node.src = source.src;
            node.controls = true;
            node.autoplay = true;
            node.playsInline = true;
        }

        stage.appendChild(node);
        lightbox.appendChild(stage);

        var close = el('button', 'fb-lightbox-close', '×');
        close.setAttribute('aria-label', 'Close video');
        close.addEventListener('click', closeLightbox);
        lightbox.appendChild(close);

        lightbox.addEventListener('click', function (event) {
            if (event.target === lightbox) closeLightbox();
        });

        root.appendChild(lightbox);

        var onKey = function (event) {
            if (event.key === 'Escape') {
                closeLightbox();
                document.removeEventListener('keydown', onKey);
            }
        };
        document.addEventListener('keydown', onKey);
    }

    // ── Styles ──

    function styleSheet() {
        var surface = DARK ? '#111827' : '#ffffff';
        var border = DARK ? '#1f2937' : '#eeeeee';
        var heading = DARK ? '#f9fafb' : '#111827';
        var body = DARK ? '#d1d5db' : '#374151';
        var muted = DARK ? '#9ca3af' : '#6b7280';
        var accent = ACCENT_FALLBACK;

        return [
            ':host{display:block;font-family:' + (O.font || 'inherit') + ';}',
            '.wall{max-width:1200px;margin:0 auto;padding:8px;}',
            '.head{text-align:center;margin:0 0 24px;}',
            '.head h2{margin:0;font-size:22px;font-weight:700;color:' + heading + ';}',
            '.head p{margin:6px 0 0;font-size:13px;color:' + muted + ';}',

            '.card{background:' + surface + ';border:1px solid ' + border + ';',
            'border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.06);',
            'cursor:pointer;transition:transform .15s ease,box-shadow .15s ease;}',
            '.card:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(0,0,0,.10);}',
            '.card:focus-visible{outline:2px solid ' + accent + ';outline-offset:2px;}',

            '.badge{display:inline-block;margin:0 0 8px;padding:2px 8px;border-radius:999px;',
            'font-size:11px;font-weight:600;background:' + accent + ';color:#fff;}',
            '.stars{color:#f59e0b;font-size:15px;letter-spacing:1px;margin:0 0 10px;}',
            '.text{margin:0 0 16px;font-size:14px;line-height:1.6;color:' + body + ';white-space:pre-line;}',
            '.author{display:flex;align-items:center;gap:10px;}',
            '.avatar{width:38px;height:38px;border-radius:50%;object-fit:cover;flex:0 0 auto;}',
            '.avatar-fallback{width:38px;height:38px;border-radius:50%;flex:0 0 auto;',
            'display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff;',
            'background:' + accent + ';}',
            '.who{min-width:0;}',
            '.who strong{display:block;font-size:13px;font-weight:600;color:' + heading + ';}',
            '.who span{display:block;font-size:12px;color:' + muted + ';}',

            '.play{display:inline-flex;align-items:center;gap:6px;margin:0 0 10px;padding:4px 10px;',
            'border-radius:999px;font-size:12px;font-weight:600;color:' + accent + ';',
            'background:' + (DARK ? 'rgba(255,255,255,.06)' : 'rgba(79,70,229,.08)') + ';}',

            '.empty,.loading{padding:32px;text-align:center;font-size:14px;color:' + muted + ';}',
            '.foot{margin:16px 0 0;text-align:center;font-size:11px;color:' + muted + ';}',

            /* masonry */
            '.masonry{columns:' + (O.columns || 3) + ' 300px;column-gap:16px;}',
            '.masonry .card{break-inside:avoid;margin:0 0 16px;}',

            /* carousel */
            '.carousel{display:flex;gap:16px;overflow-x:auto;padding-bottom:8px;',
            'scroll-snap-type:x mandatory;scrollbar-width:thin;}',
            '.carousel .card{flex:0 0 auto;width:300px;scroll-snap-align:start;}',
            '.arrows{display:flex;justify-content:center;gap:8px;margin-top:12px;}',
            '.arrows button{width:34px;height:34px;border-radius:50%;border:1px solid ' + border + ';',
            'background:' + surface + ';color:' + body + ';font-size:16px;line-height:1;cursor:pointer;}',
            '.arrows button:hover{border-color:' + accent + ';color:' + accent + ';}',

            /* single */
            '.single{max-width:560px;margin:0 auto;}',

            /* badge */
            '.badge-row{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:12px;}',
            '.stack{display:flex;}',
            '.stack .avatar,.stack .avatar-fallback{border:2px solid ' + surface + ';}',
            '.stack>*+*{margin-left:-10px;}',
            '.badge-meta{font-size:14px;color:' + body + ';}',
            '.badge-meta strong{color:' + heading + ';}',

            /* lightbox */
            '.fb-lightbox{position:fixed;inset:0;z-index:2147483000;display:flex;',
            'align-items:center;justify-content:center;background:rgba(0,0,0,.82);padding:24px;}',
            '.fb-lightbox-stage{width:min(960px,100%);aspect-ratio:16/9;background:#000;border-radius:12px;overflow:hidden;}',
            '.fb-lightbox-stage iframe,.fb-lightbox-stage video{width:100%;height:100%;border:0;display:block;}',
            '.fb-lightbox-close{position:absolute;top:16px;right:20px;width:40px;height:40px;',
            'border:0;border-radius:50%;background:rgba(255,255,255,.14);color:#fff;',
            'font-size:24px;line-height:1;cursor:pointer;}',

            '@media (max-width:640px){.masonry{columns:1;}}',
        ].join('');
    }

    // ── Card ──

    function avatarNode(testimonial, accent) {
        if (testimonial.author_avatar_url) {
            var img = el('img', 'avatar');
            img.src = testimonial.author_avatar_url;
            img.alt = '';
            img.loading = 'lazy';
            img.onerror = function () {
                var replacement = avatarFallback(testimonial, accent);
                if (img.parentNode) img.parentNode.replaceChild(replacement, img);
            };
            return img;
        }

        return avatarFallback(testimonial, accent);
    }

    function avatarFallback(testimonial, accent) {
        var node = el('div', 'avatar-fallback', initials(testimonial.author_name));
        node.style.background = accent;
        return node;
    }

    function cardNode(testimonial, accent, root, onActivate) {
        var hasVideo = !!videoSource(testimonial.video_url);

        var card = el('article', 'card');
        card.tabIndex = 0;
        card.setAttribute('role', 'button');
        card.setAttribute(
            'aria-label',
            'Testimonial from ' + testimonial.author_name + (hasVideo ? ' (video)' : ''),
        );

        if (testimonial.is_featured) {
            card.appendChild(el('span', 'badge', 'Featured'));
        }

        if (hasVideo) {
            card.appendChild(el('span', 'play', '▶ Watch video'));
        }

        if (O.ratings !== false) {
            card.appendChild(el('div', 'stars', stars(testimonial.rating)));
        }

        card.appendChild(el('p', 'text', testimonial.content));

        var author = el('div', 'author');
        author.appendChild(avatarNode(testimonial, accent));

        var who = el('div', 'who');
        who.appendChild(el('strong', null, testimonial.author_name));

        var meta = [testimonial.author_title, testimonial.author_company]
            .filter(Boolean)
            .join(' · ');

        if (meta) who.appendChild(el('span', null, meta));

        author.appendChild(who);
        card.appendChild(author);

        var activate = function () {
            onActivate(testimonial);

            if (hasVideo) openVideo(root, testimonial.video_url);
        };

        card.addEventListener('click', activate);
        card.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                activate();
            }
        });

        return card;
    }

    // ── Layouts ──

    function renderBadge(root, data, accent) {
        var row = el('div', 'badge-row');

        var stack = el('div', 'stack');
        var limit = Math.min(data.testimonials.length, 5);

        for (var i = 0; i < limit; i++) {
            stack.appendChild(avatarFallback(data.testimonials[i], accent));
        }

        if (limit > 0) row.appendChild(stack);

        var meta = el('div', 'badge-meta');

        if (O.ratings !== false) {
            var star = el('span', null, '★ ');
            star.style.color = '#f59e0b';
            meta.appendChild(star);
        }

        meta.appendChild(el('strong', null, data.average_rating.toFixed(1)));
        meta.appendChild(
            el(
                'span',
                null,
                ' · ' + data.count + (data.count === 1 ? ' review' : ' reviews'),
            ),
        );

        row.appendChild(meta);

        return row;
    }

    function renderCards(root, data, accent, className) {
        var container = el('div', className);

        for (var i = 0; i < data.testimonials.length; i++) {
            container.appendChild(
                cardNode(data.testimonials[i], accent, root, trackClick),
            );
        }

        return container;
    }

    function renderCarousel(root, data, accent) {
        var wrap = el('div');
        var track = renderCards(root, data, accent, 'carousel');
        wrap.appendChild(track);

        // Arrows are pointless with nothing to scroll to.
        if (data.testimonials.length > 1) {
            var arrows = el('div', 'arrows');

            var previous = el('button', null, '‹');
            previous.type = 'button';
            previous.setAttribute('aria-label', 'Previous testimonials');
            previous.addEventListener('click', function () {
                track.scrollBy({ left: -320, behavior: 'smooth' });
            });

            var next = el('button', null, '›');
            next.type = 'button';
            next.setAttribute('aria-label', 'Next testimonials');
            next.addEventListener('click', function () {
                track.scrollBy({ left: 320, behavior: 'smooth' });
            });

            arrows.appendChild(previous);
            arrows.appendChild(next);
            wrap.appendChild(arrows);
        }

        return wrap;
    }

    function render(root, data) {
        var accent = data.space.primary_color || ACCENT_FALLBACK;
        var wrap = el('div', 'wall');

        var heading = O.heading;

        if (heading) {
            var head = el('div', 'head');
            head.appendChild(el('h2', null, heading));

            if (O.ratings !== false && LAYOUT !== 'badge' && data.count > 0) {
                head.appendChild(
                    el(
                        'p',
                        null,
                        '★ ' +
                            data.average_rating.toFixed(1) +
                            ' average from ' +
                            data.count +
                            (data.count === 1 ? ' review' : ' reviews'),
                    ),
                );
            }

            wrap.appendChild(head);
        }

        if (data.testimonials.length === 0) {
            wrap.appendChild(
                el('div', 'empty', O.emptyText || 'No testimonials to show yet.'),
            );
        } else if (LAYOUT === 'badge') {
            wrap.appendChild(renderBadge(root, data, accent));
        } else if (LAYOUT === 'single') {
            wrap.appendChild(
                renderCards(root, data.slice(0, 1), accent, 'single'),
            );
        } else if (LAYOUT === 'carousel') {
            wrap.appendChild(renderCarousel(root, data, accent));
        } else {
            wrap.appendChild(renderCards(root, data, accent, 'masonry'));
        }

        if (O.footer !== false) {
            wrap.appendChild(el('div', 'foot', 'Powered by Feedbacker'));
        }

        root.textContent = '';
        root.appendChild(wrap);
    }

    function renderMessage(root, message) {
        var wrap = el('div', 'wall');
        wrap.appendChild(el('div', 'empty', message));

        root.textContent = '';
        root.appendChild(wrap);
    }

    // ── Tracking ──

    function send(path, payload) {
        var url = API + path;
        var body = JSON.stringify(payload);

        if (navigator.sendBeacon) {
            try {
                var blob = new Blob([body], { type: 'application/json' });
                if (navigator.sendBeacon(url, blob)) return;
            } catch (error) {
                /* fall through to fetch */
            }
        }

        try {
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: body,
                keepalive: true,
                mode: 'cors',
            }).catch(function () {});
        } catch (error) {
            /* analytics must never break the page */
        }
    }

    function trackView(spaceId) {
        send('/api/track/view', {
            space_id: spaceId,
            referrer: document.referrer || '',
            user_agent: navigator.userAgent || '',
        });
    }

    function trackClick(testimonial) {
        send('/api/track/click', {
            space_id: CFG.spaceId || '',
            testimonial_id: testimonial.id,
            referrer: document.referrer || '',
        });
    }

    // ── Mounting ──

    function containers() {
        // A saved widget is addressed by its token; the space-wide embed by slug.
        var selector = '[data-feedbacker="' + (CFG.token || CFG.slug) + '"]';
        var found = document.querySelectorAll(selector);

        if (found.length > 0) return found;

        var auto = el('div');
        auto.setAttribute('data-feedbacker', CFG.token || CFG.slug);
        document.body.appendChild(auto);

        return [auto];
    }

    function mount() {
        var roots = containers();

        for (var i = 0; i < roots.length; i++) {
            if (roots[i].shadowRoot) continue;

            var shadow = roots[i].attachShadow({ mode: 'open' });
            var style = el('style');
            style.textContent = styleSheet();
            shadow.appendChild(style);

            var host = el('div');
            host.appendChild(el('div', 'loading', 'Loading testimonials…'));
            shadow.appendChild(host);

            hydrate(host);
        }
    }

    function hydrate(root) {
        var url = API + CFG.endpoint;

        // A space-wide embed carries its filters in the URL; a saved widget has
        // them applied server-side already.
        if (!CFG.token) {
            var params = [];
            if (O.limit) params.push('limit=' + encodeURIComponent(O.limit));
            if (O.featured_only) params.push('featured_only=1');
            if (O.min_rating) params.push('min_rating=' + encodeURIComponent(O.min_rating));
            if (params.length) url += '?' + params.join('&');
        }

        fetch(url, { mode: 'cors' })
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(function (data) {
                if (!data || !data.space) {
                    renderMessage(root, 'Testimonials are unavailable right now.');
                    return;
                }

                CFG.spaceId = data.space.id;
                render(root, data);
                trackView(data.space.id);
            })
            .catch(function (error) {
                console.warn('[feedbacker] could not load testimonials:', error);
                renderMessage(
                    root,
                    O.errorText || 'Testimonials are unavailable right now.',
                );
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mount);
    } else {
        mount();
    }
})();
