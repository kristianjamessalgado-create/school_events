// ===============================
// SECTION NAVIGATION
// ===============================
let currentSection = document.querySelector('section.active');

var LANDING_SECTION_ORDER = ['public-calendar', 'hero', 'how-it-works', 'features', 'roles', 'faq'];

function isMobileView() {
    return typeof window !== 'undefined' && window.innerWidth <= 768;
}

function updateLandingScrollProgress() {
    var fill = document.getElementById('landingScrollProgress');
    if (!fill) return;
    if (isMobileView()) {
        var docEl = document.documentElement;
        var sh = docEl.scrollHeight - window.innerHeight;
        var pct = sh > 12 ? Math.min(100, Math.max(0, (window.scrollY / sh) * 100)) : 0;
        fill.style.width = pct + '%';
    } else {
        var activeId = currentSection && currentSection.id ? currentSection.id : 'public-calendar';
        var idx = LANDING_SECTION_ORDER.indexOf(activeId);
        if (idx < 0) idx = 0;
        var denom = Math.max(1, LANDING_SECTION_ORDER.length - 1);
        fill.style.width = (idx / denom) * 100 + '%';
    }
}

function syncLandingRevealDesktop() {
    if (isMobileView()) return;
    var active = currentSection || document.querySelector('section.active') || document.querySelector('section');
    document.querySelectorAll('section.reveal-scope').forEach(function (sec) {
        sec.classList.toggle('in-view', sec === active);
    });
}

function bindLandingMagnetic() {
    document.querySelectorAll('.magnetic-wrap').forEach(function (wrap) {
        wrap.addEventListener('mousemove', function (e) {
            if (window.innerWidth <= 768) return;
            var r = wrap.getBoundingClientRect();
            var x = (e.clientX - (r.left + r.width / 2)) * 0.15;
            var y = (e.clientY - (r.top + r.height / 2)) * 0.15;
            wrap.style.transform = 'translate(' + x + 'px,' + y + 'px)';
        });
        wrap.addEventListener('mouseleave', function () {
            wrap.style.transform = '';
        });
    });
}

function initFaqAccordion() {
    var acc = document.querySelector('.faq-accordion');
    if (!acc) return;
    acc.addEventListener('click', function (e) {
        var t = e.target.closest('.faq-trigger');
        if (!t || !acc.contains(t)) return;
        e.preventDefault();
        var item = t.closest('.faq-item');
        if (!item) return;
        var opening = !item.classList.contains('is-open');
        acc.querySelectorAll('.faq-item').forEach(function (fi) {
            fi.classList.remove('is-open');
            var btn = fi.querySelector('.faq-trigger');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        });
        if (opening) {
            item.classList.add('is-open');
            t.setAttribute('aria-expanded', 'true');
        }
    });
}

function initLandingPolish() {
    if (!currentSection) {
        currentSection = document.querySelector('section.active') || document.querySelector('section');
    }
    syncLandingRevealDesktop();
    updateLandingScrollProgress();

    var revealMobileObserver = null;

    function setupMobileReveal() {
        if (revealMobileObserver) {
            revealMobileObserver.disconnect();
            revealMobileObserver = null;
        }
        if (!isMobileView()) return;
        revealMobileObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting && en.target.classList.contains('reveal-scope')) {
                    en.target.classList.add('in-view');
                }
            });
        }, { threshold: 0.08, rootMargin: '-52px 0px -14% 0px' });
        document.querySelectorAll('section.reveal-scope').forEach(function (sec) {
            revealMobileObserver.observe(sec);
        });
    }

    setupMobileReveal();

    var scrollQueued = false;
    window.addEventListener('scroll', function () {
        if (!isMobileView()) return;
        if (scrollQueued) return;
        scrollQueued = true;
        window.requestAnimationFrame(function () {
            updateLandingScrollProgress();
            scrollQueued = false;
        });
    }, { passive: true });

    window.addEventListener('resize', function () {
        if (isMobileView()) {
            setupMobileReveal();
        } else {
            if (revealMobileObserver) {
                revealMobileObserver.disconnect();
                revealMobileObserver = null;
            }
            syncLandingRevealDesktop();
        }
        updateLandingScrollProgress();
    });

    bindLandingMagnetic();
    initFaqAccordion();
}

function initLandingPhotoRail() {
    var rail = document.getElementById('landingPhotoRail');
    if (!rail) return;
    var wrap = rail.closest('.landing-photo-rail-wrap');
    if (!wrap) return;

    var prevBtn = wrap.querySelector('.landing-rail-prev');
    var nextBtn = wrap.querySelector('.landing-rail-next');
    var step = function () {
        return Math.max(180, Math.floor(rail.clientWidth * 0.72));
    };

    function updateNavState() {
        var max = Math.max(0, rail.scrollWidth - rail.clientWidth);
        var pos = rail.scrollLeft;
        if (prevBtn) prevBtn.disabled = pos <= 4;
        if (nextBtn) nextBtn.disabled = pos >= (max - 4);
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            rail.scrollBy({ left: -step(), behavior: 'smooth' });
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            rail.scrollBy({ left: step(), behavior: 'smooth' });
        });
    }

    rail.addEventListener('scroll', updateNavState, { passive: true });
    window.addEventListener('resize', updateNavState);
    updateNavState();

    var cards = rail.querySelectorAll('.landing-photo-card');
    cards.forEach(function (card, idx) {
        var img = card.querySelector('.landing-photo-img');
        if (!img) return;

        var raw = card.getAttribute('data-photo-urls') || '[]';
        var photos = [];
        try {
            photos = JSON.parse(raw);
        } catch (e) {
            photos = [];
        }
        photos = Array.isArray(photos) ? photos.filter(function (u) { return typeof u === 'string' && u; }) : [];
        if (photos.length <= 1) return;

        var pointer = 0;
        var delay = 2000;
        var timerId = null;

        function rotateOnce() {
            pointer = (pointer + 1) % photos.length;
            card.classList.add('is-fading');
            window.setTimeout(function () {
                img.src = photos[pointer];
                card.classList.remove('is-fading');
            }, 240);
        }

        function startRotation() {
            if (timerId !== null) return;
            timerId = window.setInterval(rotateOnce, delay);
        }

        function stopRotation() {
            if (timerId === null) return;
            window.clearInterval(timerId);
            timerId = null;
        }

        // Rotate only while hovering/focusing the card.
        card.addEventListener('mouseenter', startRotation);
        card.addEventListener('mouseleave', stopRotation);
        card.addEventListener('focus', startRotation);
        card.addEventListener('blur', stopRotation);
    });
}

function goToSection(id) {
    const nextSection = document.getElementById(id);
    if (!nextSection) return;

    // On mobile: sections stack; scroll to the section
    if (isMobileView()) {
        nextSection.classList.add('active');
        if (currentSection && currentSection !== nextSection) {
            currentSection.classList.remove('active');
        }
        currentSection = nextSection;
        nextSection.classList.add('in-view');
        nextSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        updateLandingScrollProgress();
        return;
    }

    if (currentSection === nextSection) {
        updateLandingScrollProgress();
        return;
    }

    // Exit current section
    if (currentSection) {
        currentSection.classList.remove('active');
        currentSection.classList.add('exit-left');
    }

    // Prepare next section
    nextSection.classList.remove('exit-left');
    nextSection.classList.add('enter-left');

    // Force browser reflow
    nextSection.offsetHeight;

    // Activate next
    nextSection.classList.remove('enter-left');
    nextSection.classList.add('active');

    currentSection = nextSection;

    // Smooth scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
    syncLandingRevealDesktop();
    updateLandingScrollProgress();
}

// ===============================
// MOBILE MENU
// ===============================
function closeMobileNav() {
    const nav = document.getElementById('mobileNav');
    const btn = document.getElementById('hamburgerBtn');
    const overlay = document.getElementById('mobileNavOverlay');
    if (nav) nav.classList.remove('open');
    if (btn) btn.classList.remove('active');
    if (overlay) overlay.classList.remove('show');
    document.body.classList.remove('mobile-menu-open');
    document.body.style.overflow = '';
}

function toggleMobileNav() {
    const nav = document.getElementById('mobileNav');
    const btn = document.getElementById('hamburgerBtn');
    const overlay = document.getElementById('mobileNavOverlay');
    if (!nav || !btn) return;
    const isOpen = nav.classList.toggle('open');
    btn.classList.toggle('active', isOpen);
    if (overlay) overlay.classList.toggle('show', isOpen);
    document.body.classList.toggle('mobile-menu-open', isOpen);
    document.body.style.overflow = '';
}

document.addEventListener('DOMContentLoaded', function () {
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const registerRoleSelectModal = document.getElementById('registerRoleSelectModal');
    const registerDepartmentWrapModal = document.getElementById('registerDepartmentWrapModal');
    const registerDepartmentSelectModal = document.getElementById('registerDepartmentSelectModal');
    const registerCourseWrapModal = document.getElementById('registerCourseWrapModal');
    const registerCourseSelectModal = document.getElementById('registerCourseSelectModal');
    const registerYearLevelWrapModal = document.getElementById('registerYearLevelWrapModal');
    const registerYearLevelSelectModal = document.getElementById('registerYearLevelSelectModal');
    const loginModalPassword = document.getElementById('loginModalPassword');
    const toggleLoginModalPassword = document.getElementById('toggleLoginModalPassword');
    const registerModalPassword = document.getElementById('registerModalPassword');
    const registerModalConfirmPassword = document.getElementById('registerModalConfirmPassword');
    const registerPasswordGuide = document.getElementById('registerPasswordGuide');
    const registerPasswordMatchStatus = document.getElementById('registerPasswordMatchStatus');
    const toggleRegisterModalPassword = document.getElementById('toggleRegisterModalPassword');
    const toggleRegisterModalConfirmPassword = document.getElementById('toggleRegisterModalConfirmPassword');
    const loginModalForm = document.getElementById('loginModalForm');
    const registerModalForm = document.getElementById('registerModalForm');
    const verifyModalForm = document.getElementById('verifyModalForm');
    const verifyModalEmail = document.getElementById('verifyModalEmail');
    const loginModalMessage = document.getElementById('loginModalMessage');
    const registerModalMessage = document.getElementById('registerModalMessage');
    const verifyModalTopAlert = document.getElementById('verifyModalTopAlert');
    if (hamburgerBtn) {
        hamburgerBtn.addEventListener('click', toggleMobileNav);
    }

    initLandingPolish();
    initLandingPhotoRail();

    if (registerRoleSelectModal && registerDepartmentWrapModal) {
        const refreshCourseOptionsForDepartment = function () {
            if (!registerCourseSelectModal) return;
            var dept = registerDepartmentSelectModal ? String(registerDepartmentSelectModal.value || '') : '';
            var hasEnabled = false;
            Array.prototype.forEach.call(registerCourseSelectModal.options || [], function (opt) {
                var val = String(opt.value || '');
                if (val === '') {
                    opt.hidden = false;
                    opt.disabled = false;
                    return;
                }
                var optDept = String(opt.getAttribute('data-department') || '');
                var match = dept !== '' && optDept === dept;
                opt.hidden = dept !== '' ? !match : false;
                opt.disabled = dept === '' || !match;
                if (match) hasEnabled = true;
            });
            var selected = registerCourseSelectModal.options[registerCourseSelectModal.selectedIndex];
            if (!selected || selected.disabled) {
                registerCourseSelectModal.value = '';
            }
            if (!hasEnabled && dept !== '') {
                registerCourseSelectModal.value = '';
            }
        };

        const toggleStudentProfileFields = function () {
            var isStudent = registerRoleSelectModal.value === 'student';
            registerDepartmentWrapModal.style.display = isStudent ? 'block' : 'none';
            if (registerCourseWrapModal) {
                registerCourseWrapModal.style.display = isStudent ? 'block' : 'none';
            }
            if (registerYearLevelWrapModal) {
                registerYearLevelWrapModal.style.display = isStudent ? 'block' : 'none';
            }
            if (registerDepartmentSelectModal) {
                registerDepartmentSelectModal.required = isStudent;
                if (!isStudent) registerDepartmentSelectModal.value = '';
            }
            if (registerCourseSelectModal) {
                registerCourseSelectModal.required = isStudent;
                if (!isStudent) registerCourseSelectModal.value = '';
            }
            if (registerYearLevelSelectModal) {
                registerYearLevelSelectModal.required = isStudent;
                if (!isStudent) registerYearLevelSelectModal.value = '';
            }
            if (isStudent) {
                refreshCourseOptionsForDepartment();
            } else if (registerCourseSelectModal) {
                Array.prototype.forEach.call(registerCourseSelectModal.options || [], function (opt) {
                    opt.hidden = false;
                    opt.disabled = false;
                });
            }
        };
        registerRoleSelectModal.addEventListener('change', toggleStudentProfileFields);
        if (registerDepartmentSelectModal) {
            registerDepartmentSelectModal.addEventListener('change', refreshCourseOptionsForDepartment);
        }
        toggleStudentProfileFields();
    }

    function bindEyeToggle(buttonEl, inputEl) {
        if (!buttonEl || !inputEl) return;
        buttonEl.addEventListener('click', function () {
            const show = inputEl.type === 'password';
            inputEl.type = show ? 'text' : 'password';
            buttonEl.setAttribute('aria-pressed', show ? 'true' : 'false');
            buttonEl.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            var icon = buttonEl.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye', !show);
                icon.classList.toggle('fa-eye-slash', show);
            }
            inputEl.focus();
        });
    }

    bindEyeToggle(toggleLoginModalPassword, loginModalPassword);
    bindEyeToggle(toggleRegisterModalPassword, registerModalPassword);
    bindEyeToggle(toggleRegisterModalConfirmPassword, registerModalConfirmPassword);

    function updateRegisterPasswordFeedback() {
        if (!registerModalPassword || !registerModalConfirmPassword) return;
        var p = String(registerModalPassword.value || '');
        var c = String(registerModalConfirmPassword.value || '');
        var hasUpper = /[A-Z]/.test(p);
        var hasSpecial = /[\W_]/.test(p);
        var longEnough = p.length >= 8;

        if (registerPasswordGuide) {
            if (p.length === 0) {
                registerPasswordGuide.textContent = 'Password guide: at least 8 characters, with 1 uppercase letter and 1 special character.';
            } else if (longEnough && hasUpper && hasSpecial) {
                registerPasswordGuide.textContent = 'Password strength requirement met.';
            } else {
                var missing = [];
                if (!longEnough) missing.push('8+ characters');
                if (!hasUpper) missing.push('1 uppercase');
                if (!hasSpecial) missing.push('1 special character');
                registerPasswordGuide.textContent = 'Missing: ' + missing.join(', ') + '.';
            }
        }

        if (!registerPasswordMatchStatus) return;
        if (p.length === 0 && c.length === 0) {
            registerPasswordMatchStatus.textContent = '';
            registerPasswordMatchStatus.style.display = 'none';
            registerPasswordMatchStatus.classList.remove('match', 'mismatch');
            return;
        }
        registerPasswordMatchStatus.style.display = 'block';
        if (c.length === 0) {
            registerPasswordMatchStatus.textContent = 'Confirm your password to check if it matches.';
            registerPasswordMatchStatus.classList.remove('match');
            registerPasswordMatchStatus.classList.add('mismatch');
            return;
        }
        if (p === c) {
            registerPasswordMatchStatus.textContent = 'Passwords match.';
            registerPasswordMatchStatus.classList.remove('mismatch');
            registerPasswordMatchStatus.classList.add('match');
        } else {
            registerPasswordMatchStatus.textContent = 'Passwords do not match.';
            registerPasswordMatchStatus.classList.remove('match');
            registerPasswordMatchStatus.classList.add('mismatch');
        }
    }

    if (registerModalPassword) registerModalPassword.addEventListener('input', updateRegisterPasswordFeedback);
    if (registerModalConfirmPassword) registerModalConfirmPassword.addEventListener('input', updateRegisterPasswordFeedback);
    updateRegisterPasswordFeedback();

    function setInlineMessage(el, type, text) {
        if (!el) return;
        if (!text) {
            el.textContent = '';
            el.style.display = 'none';
            el.classList.remove('error', 'success');
            return;
        }
        el.textContent = text;
        el.classList.remove('error', 'success');
        el.classList.add(type === 'success' ? 'success' : 'error');
        el.style.display = 'block';
    }

    function setVerifyTopAlert(type, text) {
        if (!verifyModalTopAlert) return;
        if (!text) {
            verifyModalTopAlert.textContent = '';
            verifyModalTopAlert.classList.remove('error', 'success');
            verifyModalTopAlert.style.display = 'none';
            return;
        }
        verifyModalTopAlert.textContent = text;
        verifyModalTopAlert.classList.remove('error', 'success');
        verifyModalTopAlert.classList.add(type === 'success' ? 'success' : 'error');
        verifyModalTopAlert.style.display = 'block';
    }

    function clearAllModalMessages() {
        setInlineMessage(loginModalMessage, '', '');
        setInlineMessage(registerModalMessage, '', '');
        setVerifyTopAlert('', '');
    }

    function resolveFinalUrl(rawUrl) {
        try {
            return new URL(rawUrl, window.location.origin);
        } catch (e) {
            return null;
        }
    }

    function getRoleHomeUrl() {
        var base = window.EVENTIFY_BASE_URL || '/school_events';
        return base + '/index.php';
    }

    function safeNavigate(urlLike) {
        var target = (typeof urlLike === 'string') ? urlLike : '';
        if (!target || target.indexOf('[object') !== -1) {
            window.location.href = getRoleHomeUrl();
            return;
        }
        window.location.href = target;
    }

    function setFormSubmitting(formEl, submitting, label) {
        if (!formEl) return;
        var btn = formEl.querySelector('button[type="submit"]');
        if (!btn) return;
        if (submitting) {
            if (!btn.dataset.originalText) btn.dataset.originalText = btn.textContent || 'Submit';
            btn.disabled = true;
            btn.textContent = label || 'Please wait...';
        } else {
            btn.disabled = false;
            if (btn.dataset.originalText) btn.textContent = btn.dataset.originalText;
        }
    }

    function extractRedirectFromAuthHtml(htmlText) {
        if (typeof htmlText !== 'string' || htmlText === '') return '';
        var m = htmlText.match(/window\.top\.location\.href\s*=\s*([^;]+);/i);
        if (!m || !m[1]) return '';
        var rhs = m[1].trim();
        // Backend writes: window.top.location.href = "<url>";
        // Try JSON parsing first so escaped slashes are decoded properly.
        try {
            var parsed = JSON.parse(rhs);
            if (typeof parsed === 'string' && parsed) return parsed;
        } catch (e) {
            // ignore and try fallback below
        }
        // Fallback: strip quotes and unescape common slash escaping.
        rhs = rhs.replace(/^['"]|['"]$/g, '').replace(/\\\//g, '/');
        return rhs;
    }

    async function submitModalForm(formEl, onHandled) {
        if (!formEl) return;
        const body = new FormData(formEl);
        const response = await fetch(formEl.action, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            redirect: 'follow'
        });
        const finalUrl = resolveFinalUrl(response.url);
        const responseText = await response.text();
        if (onHandled) onHandled(finalUrl, response, responseText);
    }

    // Hard reliability fix: do NOT AJAX-submit login.
    // Normal form submit ensures server session + role redirects always work.
    if (loginModalForm) {
        loginModalForm.addEventListener('submit', function () {
            clearAllModalMessages();
            setFormSubmitting(loginModalForm, true, 'Logging in...');
        });
    }

    // Hard reliability fix for registration too: do normal submit.
    // Backend already handles validation + redirects with clear error messages.
    if (registerModalForm) {
        registerModalForm.addEventListener('submit', function () {
            clearAllModalMessages();
            setFormSubmitting(registerModalForm, true, 'Registering...');
        });
    }

    // Hard reliability fix for OTP verify too: do normal submit (same as login/register).
    // AJAX fetch + redirect parsing was dropping errors and breaking CSRF on some browsers.
    if (verifyModalForm) {
        verifyModalForm.addEventListener('submit', function () {
            clearAllModalMessages();
            setFormSubmitting(verifyModalForm, true, 'Verifying...');
        });
    }

    // Re-open specific auth modal after backend redirect from form validation.
    if (window.AUTH_MODAL === 'register') {
        openRegisterModal();
        var serverErrEl = document.getElementById('registerModalMessageServer');
        if (window.AUTH_ERROR && registerModalMessage && !serverErrEl) {
            setInlineMessage(registerModalMessage, 'error', window.AUTH_ERROR);
        }
        if (window.AUTH_ERROR && /password/i.test(window.AUTH_ERROR)) {
            if (registerModalPassword) registerModalPassword.value = '';
            if (registerModalConfirmPassword) registerModalConfirmPassword.value = '';
        }
    } else if (window.AUTH_MODAL === 'verify') {
        openVerifyModal({
            purpose: window.VERIFY_PURPOSE || 'register',
            email: window.VERIFY_EMAIL || '',
            error: window.AUTH_ERROR || '',
            success: window.AUTH_SUCCESS || ''
        });
    } else if (window.AUTH_MODAL === 'login') {
        openLoginModal();
        var loginServerEl = document.getElementById('loginModalMessageServer');
        if (!loginServerEl && window.AUTH_ERROR && loginModalMessage) {
            setInlineMessage(loginModalMessage, 'error', window.AUTH_ERROR);
        } else if (!loginServerEl && window.AUTH_SUCCESS && loginModalMessage) {
            setInlineMessage(loginModalMessage, 'success', window.AUTH_SUCCESS);
        }
    }

    function promptLogin(loginUrl) {
        if (!loginUrl) return;
        if (window.innerWidth > 768) {
            openLoginModal();
        } else {
            window.location.href = loginUrl;
        }
    }

    // Login: on desktop open modal, on mobile go to full login page
    document.querySelectorAll('.login-trigger').forEach(function (el) {
        el.addEventListener('click', function (e) {
            var loginUrl = el.getAttribute('data-login-url');
            if (window.innerWidth > 768) {
                // Desktop: show modal, do not leave landing page
                e.preventDefault();
                openLoginModal();
            } else if (loginUrl) {
                // Mobile: navigate to full login/register page
                e.preventDefault();
                window.location.href = loginUrl;
            }
        });
    });


    // Public calendar: show events but require login on interaction
    try {
        var calEl = document.getElementById('publicCalendar');
        var monthEl = document.getElementById('publicCalendarMonth');
        if (calEl && window.FullCalendar) {
            var events = Array.isArray(window.PUBLIC_CALENDAR_EVENTS) ? window.PUBLIC_CALENDAR_EVENTS : [];
            var loginUrl = window.PUBLIC_LOGIN_URL || '';
            var syncMonth = function (cal) {
                if (!monthEl || !cal) return;
                monthEl.textContent = (cal.view && cal.view.title) ? cal.view.title : monthEl.textContent;
            };

            var calendar = new FullCalendar.Calendar(calEl, {
                initialView: 'dayGridMonth',
                height: 'auto',
                fixedWeekCount: false,
                showNonCurrentDates: true,
                navLinks: false,
                selectable: false,
                nowIndicator: true,
                eventColor: '#047857',
                eventTextColor: '#fefce8',
                events: events,
                eventDidMount: function (info) {
                    if (typeof eventifyApplyCalendarEventMount === 'function') {
                        eventifyApplyCalendarEventMount(info);
                        return;
                    }
                    var st = String((info.event.extendedProps && info.event.extendedProps.status) || '').toLowerCase();
                    if (st === 'closed' || st === 'completed') {
                        info.el.style.backgroundColor = '#64748b';
                        info.el.style.borderColor = '#475569';
                    }
                },
                dateClick: function () {
                    promptLogin(loginUrl);
                },
                eventClick: function (info) {
                    if (info && info.jsEvent) info.jsEvent.preventDefault();
                    promptLogin(loginUrl);
                },
                datesSet: function () {
                    syncMonth(calendar);
                }
            });
            calendar.render();
            syncMonth(calendar);

            var syncLandingVideoToCalendar = function () {
                var card = document.querySelector('#public-calendar .public-calendar-card');
                var wrap = document.querySelector('.landing-calendar-video-wrap');
                var video = wrap && wrap.querySelector('.landing-calendar-video');
                if (!card || !wrap || !video) return;
                wrap.style.width = card.offsetWidth + 'px';
                wrap.style.height = card.offsetHeight + 'px';
            };

            syncLandingVideoToCalendar();
            calendar.on('datesSet', syncLandingVideoToCalendar);
            window.addEventListener('resize', syncLandingVideoToCalendar);
            if (typeof ResizeObserver !== 'undefined') {
                var cardEl = document.querySelector('#public-calendar .public-calendar-card');
                if (cardEl) {
                    var ro = new ResizeObserver(syncLandingVideoToCalendar);
                    ro.observe(cardEl);
                }
            }
        }
    } catch (err) {
        // ignore calendar init failures on landing
    }
});

// ===============================
// MODAL LOGIC
// ===============================
function closeAllLegalModals() {
    var p = document.getElementById('legalPrivacyModal');
    var t = document.getElementById('legalTermsModal');
    if (p) {
        p.style.display = 'none';
        p.setAttribute('aria-hidden', 'true');
    }
    if (t) {
        t.style.display = 'none';
        t.setAttribute('aria-hidden', 'true');
    }
}

function openLegalPrivacyModal() {
    var el = document.getElementById('legalPrivacyModal');
    if (!el) return;
    closeLegalTermsModal();
    el.style.display = 'flex';
    el.setAttribute('aria-hidden', 'false');
}

function closeLegalPrivacyModal() {
    var el = document.getElementById('legalPrivacyModal');
    if (el) {
        el.style.display = 'none';
        el.setAttribute('aria-hidden', 'true');
    }
}

function openLegalTermsModal() {
    var el = document.getElementById('legalTermsModal');
    if (!el) return;
    closeLegalPrivacyModal();
    el.style.display = 'flex';
    el.setAttribute('aria-hidden', 'false');
}

function closeLegalTermsModal() {
    var el = document.getElementById('legalTermsModal');
    if (el) {
        el.style.display = 'none';
        el.setAttribute('aria-hidden', 'true');
    }
}

function openLoginModal() {
    var modal = document.getElementById('loginModal');
    var registerModal = document.getElementById('registerModal');
    var verifyModal = document.getElementById('verifyModal');
    closeAllLegalModals();
    if (registerModal) registerModal.style.display = 'none';
    if (verifyModal) verifyModal.style.display = 'none';
    if (modal) modal.style.display = 'flex';
}

function closeLoginModal() {
    document.getElementById('loginModal').style.display = 'none';
    closeAllLegalModals();
}

function openRegisterModal() {
    var modal = document.getElementById('loginModal');
    var registerModal = document.getElementById('registerModal');
    var verifyModal = document.getElementById('verifyModal');
    closeAllLegalModals();
    if (modal) modal.style.display = 'none';
    if (verifyModal) verifyModal.style.display = 'none';
    if (registerModal) registerModal.style.display = 'flex';
}

function closeRegisterModal() {
    var registerModal = document.getElementById('registerModal');
    if (registerModal) registerModal.style.display = 'none';
    closeAllLegalModals();
}

function openVerifyModal(opts) {
    opts = opts || {};
    var modal = document.getElementById('loginModal');
    var registerModal = document.getElementById('registerModal');
    var verifyModal = document.getElementById('verifyModal');
    var purposeInput = document.getElementById('verifyModalPurpose');
    var titleEl = document.getElementById('verifyModalTitle');
    var subtitleEl = document.getElementById('verifyModalSubtitle');
    var emailInput = document.getElementById('verifyModalEmail');
    var otpInput = document.getElementById('verifyOtpCode');
    var topAlert = document.getElementById('verifyModalTopAlert');
    var resendPurpose = document.getElementById('verifyResendPurpose');
    var resendEmail = document.getElementById('verifyResendEmail');
    var purpose = opts.purpose === 'reactivate' ? 'reactivate' : 'register';
    closeAllLegalModals();
    if (modal) modal.style.display = 'none';
    if (registerModal) registerModal.style.display = 'none';
    if (purposeInput) purposeInput.value = purpose;
    if (titleEl) {
        titleEl.textContent = purpose === 'register' ? 'Verify your email' : 'Verify reactivation OTP';
    }
    if (subtitleEl) {
        subtitleEl.textContent = purpose === 'register'
            ? 'Enter the 6-digit code we sent to your email. After verification, super admin will approve your account.'
            : 'Enter the code sent to your registered email.';
    }
    if (emailInput && opts.email) {
        emailInput.value = opts.email;
        if (purpose === 'register') {
            emailInput.readOnly = true;
        }
    }
    if (resendPurpose) {
        resendPurpose.value = purpose;
    }
    if (resendEmail && emailInput && emailInput.value) {
        resendEmail.value = emailInput.value;
    }
    if (otpInput && !opts.keepOtp) {
        otpInput.value = '';
    }
    if (topAlert) {
        var existingMsg = (topAlert.textContent || '').trim();
        if (opts.error) {
            topAlert.textContent = opts.error;
            topAlert.classList.remove('success');
            topAlert.classList.add('error');
            topAlert.style.display = 'block';
        } else if (opts.success) {
            topAlert.textContent = opts.success;
            topAlert.classList.remove('error');
            topAlert.classList.add('success');
            topAlert.style.display = 'block';
        } else if (!existingMsg) {
            topAlert.textContent = '';
            topAlert.classList.remove('error', 'success');
            topAlert.style.display = 'none';
        }
    }
    if (verifyModal) verifyModal.style.display = 'flex';
}

function closeVerifyModal() {
    var verifyModal = document.getElementById('verifyModal');
    if (verifyModal) verifyModal.style.display = 'none';
    closeAllLegalModals();
}

window.onclick = function(e) {
    const modal = document.getElementById('loginModal');
    const registerModal = document.getElementById('registerModal');
    const verifyModal = document.getElementById('verifyModal');
    const legalPrivacy = document.getElementById('legalPrivacyModal');
    const legalTerms = document.getElementById('legalTermsModal');
    if (e.target === modal) closeLoginModal();
    if (e.target === registerModal) closeRegisterModal();
    if (e.target === verifyModal) closeVerifyModal();
    if (e.target === legalPrivacy) closeLegalPrivacyModal();
    if (e.target === legalTerms) closeLegalTermsModal();
};

// Legal modals: capture phase so footer/register triggers never fall through to navigation.
document.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-legal-open]');
    if (!opener) return;
    e.preventDefault();
    e.stopPropagation();
    var kind = opener.getAttribute('data-legal-open');
    if (kind === 'privacy') {
        openLegalPrivacyModal();
    } else if (kind === 'terms') {
        openLegalTermsModal();
    }
}, true);

// ===============================
// SPLINE EYES FOLLOW MOUSE
// ===============================
const spline = document.querySelector('spline-viewer');

if (spline) {
    spline.addEventListener('load', async (e) => {
        try {
            // Wait for the scene to fully initialize
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            const app = e.target;
            
            // Try multiple ways to access the Spline scene
            let scene = null;
            let eyes = null;
            
            // Method 1: Try app.spline
            if (app.spline) {
                scene = app.spline;
            }
            // Method 2: Try app.application
            else if (app.application && app.application.scene) {
                scene = app.application.scene;
            }
            // Method 3: Try accessing through the event detail
            else if (e.detail && e.detail.scene) {
                scene = e.detail.scene;
            }
            
            if (!scene) {
                console.warn("Spline scene not found. Trying alternative access...");
                await new Promise(resolve => setTimeout(resolve, 500));
                if (spline.application) {
                    scene = spline.application.scene;
                }
            }
            
            if (!scene) {
                console.warn("Could not access Spline scene. Eye tracking disabled.");
                return;
            }

            // Try different possible names for the eyes object
            const possibleNames = ['Eyes', 'eyes', 'Eye', 'eye', 'EyesGroup', 'eyesGroup', 'Eyes_Group', 'EyesGroup1'];
            
            for (const name of possibleNames) {
                try {
                    if (scene.findObjectByName) {
                        eyes = scene.findObjectByName(name);
                    } else if (scene.getObjectByName) {
                        eyes = scene.getObjectByName(name);
                    }
                    if (eyes) {
                        console.log(`✓ Found eyes object: ${name}`);
                        break;
                    }
                } catch (err) {
                    // Continue searching
                }
            }

            // If not found by name, try recursive search
            if (!eyes) {
                const searchForEyes = (obj, depth = 0) => {
                    if (depth > 10 || !obj) return null;
                    
                    try {
                        if (obj.name && (obj.name.toLowerCase().includes('eye') || obj.name.toLowerCase().includes('pupil'))) {
                            return obj;
                        }
                        
                        if (obj.children && obj.children.length > 0) {
                            for (const child of obj.children) {
                                const found = searchForEyes(child, depth + 1);
                                if (found) return found;
                            }
                        }
                    } catch (err) {
                        // Skip objects that can't be accessed
                    }
                    return null;
                };
                
                eyes = searchForEyes(scene);
                if (eyes) {
                    console.log(`✓ Found eyes object recursively: ${eyes.name}`);
                }
            }

            if (!eyes) {
                console.warn("⚠ Could not find eyes object. Please check the object name in Spline.");
                return;
            }

            // Mouse tracking with smooth movement
            let targetRotationX = 0;
            let targetRotationY = 0;
            let currentRotationX = 0;
            let currentRotationY = 0;

            const handleMouseMove = (e) => {
                const x = (e.clientX / window.innerWidth - 0.5) * 2;
                const y = (0.5 - e.clientY / window.innerHeight) * 2;

                targetRotationY = x * 0.4;
                targetRotationX = y * 0.4;
            };

            document.addEventListener('mousemove', handleMouseMove);

            // Smooth animation loop
            function animateEyes() {
                currentRotationX += (targetRotationX - currentRotationX) * 0.15;
                currentRotationY += (targetRotationY - currentRotationY) * 0.15;

                try {
                    if (eyes) {
                        if (eyes.rotation !== undefined) {
                            eyes.rotation.y = currentRotationY;
                            eyes.rotation.x = currentRotationX;
                        } else if (eyes.rotationY !== undefined) {
                            eyes.rotationY = currentRotationY;
                            eyes.rotationX = currentRotationX;
                        }
                    }
                } catch (err) {
                    console.warn("Error updating eye rotation:", err);
                }

                requestAnimationFrame(animateEyes);
            }

            animateEyes();
            console.log("✓ Eye tracking initialized successfully");

        } catch (error) {
            console.error("✗ Error initializing eye tracking:", error);
        }
    });
}
