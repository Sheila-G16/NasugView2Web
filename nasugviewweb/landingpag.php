<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NasugView | Discover, Connect, Support</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --primary-color:#001a47;
    --primary-soft:#0d3b78;
    --accent-color:#00b8d9;
    --gold-color:#ffd45a;
    --ink-color:#102033;
    --muted-color:#667085;
    --surface-color:#ffffff;
    --page-color:#f4f8fc;
    --line-color:rgba(0,26,71,.12);
    --shadow:0 22px 60px rgba(0,26,71,.14);
}

* {
    box-sizing:border-box;
}

html {
    scroll-behavior:smooth;
    scroll-padding-top:86px;
}

body {
    margin:0;
    font-family:'Poppins', sans-serif;
    color:var(--ink-color);
    background:var(--page-color);
}

a {
    text-decoration:none;
}

.site-header {
    position:fixed;
    top:0;
    left:0;
    right:0;
    z-index:1000;
    background:rgba(255,255,255,.92);
    border-bottom:1px solid rgba(0,26,71,.08);
    backdrop-filter:blur(14px);
}

.navbar {
    min-height:76px;
}

.brand-lockup {
    display:flex;
    align-items:center;
    gap:.75rem;
    color:var(--primary-color);
    font-weight:800;
    letter-spacing:0;
}

.brand-lockup img {
    width:142px;
    height:auto;
    filter:drop-shadow(0 5px 10px rgba(0,26,71,.12));
}

.nav-link {
    color:#233b5d;
    font-weight:600;
    font-size:.94rem;
    padding:.7rem .9rem !important;
    border-radius:8px;
}

.nav-link:hover,
.nav-link.active {
    color:var(--primary-color);
    background:#eef6ff;
}

.btn-main,
.btn-soft {
    border-radius:8px;
    font-weight:700;
    padding:.78rem 1.1rem;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:.5rem;
}

.btn-main {
    border:0;
    color:#fff;
    background:linear-gradient(135deg, var(--primary-color), #0047a8);
    box-shadow:0 14px 28px rgba(0,26,71,.22);
}

.btn-main:hover {
    color:#fff;
    transform:translateY(-1px);
    box-shadow:0 18px 34px rgba(0,26,71,.28);
}

.btn-soft {
    color:var(--primary-color);
    background:#fff;
    border:1px solid var(--line-color);
}

.btn-soft:hover {
    color:var(--primary-color);
    background:#eef6ff;
}

.hero {
    position:relative;
    min-height:92vh;
    padding:132px 0 74px;
    overflow:hidden;
    background:
        linear-gradient(135deg, rgba(0,26,71,.95), rgba(0,70,156,.86)),
        radial-gradient(circle at 78% 18%, rgba(0,184,217,.26), transparent 34%),
        #001a47;
    color:#fff;
}

.hero::after {
    content:"";
    position:absolute;
    left:0;
    right:0;
    bottom:-72px;
    height:160px;
    background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 1440 180' preserveAspectRatio='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath fill='white' d='M0 60C145 70 235 92 365 82C505 72 585 48 725 60C875 73 980 96 1110 84C1242 72 1336 56 1440 66V180H0Z'/%3E%3C/svg%3E");
    background-repeat:no-repeat;
    background-size:100% 100%;
    z-index:1;
    animation:smoothWaveBase 5.5s ease-in-out infinite alternate;
}

.hero::before {
    content:"";
    display:none;
}

.water-wave {
    display:none;
}

.water-wave.wave-a {
    display:none;
}

.water-wave.wave-b {
    display:none;
}

.water-wave.wave-c {
    display:none;
}

@keyframes smoothWaveBase {
    0% {
        transform:translate3d(0, 0, 0);
    }
    100% {
        transform:translate3d(0, -5px, 0) scaleY(1.03);
    }
}

@keyframes smoothWaveFade {
    0% {
        transform:translate3d(0, 0, 0);
    }
    100% {
        transform:translate3d(0, 4px, 0) scaleY(.97);
    }
}

@keyframes smoothWaveLayer {
    0% {
        transform:translate3d(0, 0, 0);
    }
    100% {
        transform:translate3d(0, 5px, 0) scaleY(.98);
    }
}

@keyframes smoothWaveSoft {
    0% {
        transform:translate3d(0, 0, 0);
    }
    100% {
        transform:translate3d(0, -4px, 0) scaleY(1.02);
    }
}

.hero-content {
    position:relative;
    z-index:2;
}

.floating-circle {
    position:absolute;
    z-index:1;
    pointer-events:none;
    width:104px;
    height:104px;
    border-radius:50%;
    background:rgba(189,187,219,.14);
    border:0;
    animation:circleFloat 6s ease-in-out infinite;
}

.circle-one {
    top:12%;
    right:10%;
}

.circle-two {
    width:78px;
    height:78px;
    bottom:18%;
    right:78%;
    animation-delay:1s;
}

.circle-three {
    width:150px;
    height:150px;
    top:24%;
    right:-28px;
    opacity:.55;
    animation-delay:.45s;
}

.circle-four {
    width:68px;
    height:68px;
    top:20%;
    left:6%;
    opacity:.75;
    animation-delay:1.4s;
}

@keyframes circleFloat {
    0%, 100% {
        transform:translateY(0) rotate(0deg);
    }
    50% {
        transform:translateY(-20px) rotate(180deg);
    }
}

.eyebrow {
    display:inline-flex;
    align-items:center;
    gap:.55rem;
    padding:.48rem .72rem;
    border-radius:999px;
    color:#e8f7ff;
    background:rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.18);
    font-weight:700;
    font-size:.82rem;
}

.hero h1 {
    margin:1.1rem 0 1rem;
    font-size:clamp(2.45rem, 5vw, 5.15rem);
    line-height:1.02;
    font-weight:800;
    letter-spacing:0;
}

.hero p {
    max-width:650px;
    color:rgba(255,255,255,.84);
    font-size:1.08rem;
    line-height:1.8;
}

.brand-tagline {
    margin:1.35rem 0 0;
    display:inline-flex;
    flex-wrap:wrap;
    align-items:center;
    gap:.55rem;
    color:#fff;
    font-weight:800;
    font-size:clamp(1.05rem, 2vw, 1.45rem);
}

.brand-tagline .star {
    color:var(--gold-color);
    font-size:.7em;
}

.brand-tagline-sub {
    display:block;
    margin-top:.35rem;
    color:rgba(255,255,255,.78);
    font-weight:600;
}

.online-hero-art {
    position:relative;
    min-height:342px;
    overflow:visible;
    border-radius:8px;
    background:transparent;
}

.online-hero-art::before {
    content:"";
    position:absolute;
    right:18px;
    top:-2px;
    width:330px;
    height:330px;
    border-radius:50%;
    border:1px solid rgba(255,255,255,.38);
    box-shadow:
        0 0 0 34px rgba(255,255,255,.05),
        0 0 0 66px rgba(255,255,255,.032),
        inset 0 0 34px rgba(255,255,255,.08);
    animation:networkGlow 4.8s ease-in-out infinite alternate;
}

.digital-orbit {
    position:absolute;
    inset:0;
    pointer-events:none;
}

.digital-line {
    position:absolute;
    left:18%;
    right:7%;
    height:2px;
    background:linear-gradient(90deg, transparent, rgba(255,255,255,.8), rgba(117,221,245,.9), transparent);
    transform-origin:center;
    opacity:.9;
}

.digital-line.line-one { top:24%; transform:rotate(-18deg); }
.digital-line.line-two { top:42%; transform:rotate(6deg); }
.digital-line.line-three { top:55%; transform:rotate(-7deg); }

.digital-node {
    position:absolute;
    width:13px;
    height:13px;
    border-radius:50%;
    background:#ffd45a;
    box-shadow:0 0 0 8px rgba(255,212,90,.16), 0 0 24px rgba(255,212,90,.7);
    animation:nodePulse 2.5s ease-in-out infinite;
}

.node-a { left:28%; top:22%; }
.node-b { right:30%; top:15%; animation-delay:.45s; }
.node-c { right:14%; bottom:39%; animation-delay:.9s; }

.floating-ui {
    position:absolute;
    display:flex;
    align-items:center;
    gap:.55rem;
    padding:.62rem .76rem;
    border-radius:8px;
    color:var(--primary-color);
    background:rgba(255,255,255,.94);
    box-shadow:0 16px 34px rgba(0,16,48,.2);
    font-size:.7rem;
    font-weight:800;
    animation:cardFloat 4.2s ease-in-out infinite;
}

.floating-ui i {
    color:var(--accent-color);
}

.ui-store { top:20%; left:7%; }
.ui-review { top:8%; right:9%; animation-delay:.6s; }
.ui-chat { left:25%; bottom:24%; animation-delay:1.1s; }

.hero-laptop {
    position:absolute;
    right:23%;
    bottom:0;
    width:258px;
    height:74px;
    border-radius:10px 10px 24px 24px;
    background:linear-gradient(180deg, #f3fbff, #9fcfe0);
    box-shadow:0 20px 36px rgba(0,10,34,.24);
}

.hero-laptop::before {
    content:"";
    position:absolute;
    left:30px;
    right:30px;
    top:-108px;
    height:120px;
    border:5px solid rgba(255,255,255,.8);
    border-radius:8px;
    background:
        linear-gradient(90deg, rgba(0,26,71,.08) 1px, transparent 1px),
        linear-gradient(rgba(0,26,71,.08) 1px, transparent 1px),
        radial-gradient(circle at 60% 40%, rgba(255,255,255,.64), transparent 18%),
        linear-gradient(135deg, #e8f8ff, #68b8d5);
    background-size:26px 26px, 26px 26px, 100% 100%;
}

.screen-card {
    position:absolute;
    left:27%;
    right:42%;
    bottom:106px;
    height:62px;
    border-radius:8px;
    background:#fff;
    box-shadow:0 12px 26px rgba(0,26,71,.16);
    animation:cardFloat 3.6s ease-in-out infinite;
}

.screen-card::before,
.screen-card::after {
    content:"";
    position:absolute;
    left:12px;
    right:12px;
    height:8px;
    border-radius:999px;
    background:#b7dff0;
}

.screen-card::before { top:14px; }
.screen-card::after {
    top:32px;
    right:38px;
    background:#ffd45a;
}

.online-person {
    position:absolute;
    bottom:0;
    width:132px;
    height:234px;
    animation:personFloat 4.6s ease-in-out infinite;
}

.person-business {
    right:28%;
}

.person-consumer {
    right:4%;
    animation-delay:.55s;
}

.online-person::before {
    content:"";
    position:absolute;
    left:34px;
    top:0;
    width:58px;
    height:58px;
    border-radius:50%;
    background:#f1b087;
    box-shadow:
        0 -15px 0 -6px #17334c,
        -20px 34px 0 -18px rgba(255,255,255,.86),
        20px 34px 0 -18px rgba(255,255,255,.86);
}

.online-person::after {
    content:"";
    position:absolute;
    left:18px;
    right:18px;
    top:64px;
    bottom:0;
    border-radius:48px 48px 12px 12px;
    background:
        linear-gradient(90deg, transparent 0 16%, #dcecf5 17% 32%, transparent 33% 67%, #dcecf5 68% 83%, transparent 84%),
        linear-gradient(180deg, #e8f4fb 0 42%, #20659a 43% 100%);
}

.person-consumer::before {
    background:#e6a27a;
    box-shadow:
        0 -18px 0 -5px #101a28,
        -20px 34px 0 -18px #101a28,
        20px 34px 0 -18px #101a28;
}

.person-consumer::after {
    background:
        linear-gradient(90deg, transparent 0 18%, #101a28 19% 31%, transparent 32% 68%, #101a28 69% 81%, transparent 82%),
        linear-gradient(180deg, #182436 0 100%);
}

@keyframes networkGlow {
    from {
        transform:scale(.98);
        opacity:.72;
    }
    to {
        transform:scale(1.02);
        opacity:1;
    }
}

@keyframes nodePulse {
    0%, 100% {
        transform:scale(1);
        opacity:.78;
    }
    50% {
        transform:scale(1.35);
        opacity:1;
    }
}

@keyframes cardFloat {
    0%, 100% {
        transform:translateY(0);
    }
    50% {
        transform:translateY(-10px);
    }
}

@keyframes personFloat {
    0%, 100% {
        transform:translateY(0);
    }
    50% {
        transform:translateY(-7px);
    }
}

.hero-panel {
    background:rgba(255,255,255,.96);
    color:var(--ink-color);
    border-radius:8px;
    padding:1.2rem;
    box-shadow:var(--shadow);
}

.hero-logo-card {
    border-radius:8px;
    padding:2rem;
    background:linear-gradient(180deg, #fff, #edf6ff);
    text-align:center;
    border:1px solid rgba(0,26,71,.08);
}

.hero-logo-card img {
    width:min(270px, 88%);
    margin-bottom:1.1rem;
}


.app-preview {
    position:relative;
    margin-top:1rem;
    display:grid;
    grid-template-columns:1fr .72fr;
    gap:.85rem;
    align-items:end;
}

.browser-frame,
.phone-frame {
    border:1px solid rgba(0,26,71,.12);
    border-radius:8px;
    overflow:hidden;
    background:#fff;
    box-shadow:0 16px 34px rgba(0,26,71,.12);
}

.browser-bar {
    display:flex;
    gap:.35rem;
    padding:.65rem;
    background:#eef6ff;
}

.browser-bar span {
    width:9px;
    height:9px;
    border-radius:50%;
    background:#7ab6ff;
}

.preview-map {
    min-height:184px;
    padding:1rem;
    background:
        linear-gradient(90deg, rgba(0,184,217,.08) 1px, transparent 1px),
        linear-gradient(rgba(0,184,217,.08) 1px, transparent 1px),
        #f8fcff;
    background-size:32px 32px;
    position:relative;
}

.map-pin {
    position:absolute;
    width:34px;
    height:34px;
    display:grid;
    place-items:center;
    color:#fff;
    background:var(--primary-color);
    border-radius:50% 50% 50% 8px;
    transform:rotate(-45deg);
    box-shadow:0 10px 22px rgba(0,26,71,.25);
    animation:pinPulse 2.4s ease-in-out infinite;
}

.map-pin i {
    transform:rotate(45deg);
    font-size:.8rem;
}

.pin-one { left:22%; top:31%; }
.pin-two { right:22%; top:21%; background:#00a6c8; }
.pin-three { left:52%; bottom:20%; background:#1f7a4d; }

@keyframes pinPulse {
    0%, 100% {
        translate:0 0;
    }
    50% {
        translate:0 -6px;
    }
}

.recommendation-card {
    position:absolute;
    left:1rem;
    right:1rem;
    bottom:1rem;
    padding:.8rem;
    border-radius:8px;
    background:#fff;
    box-shadow:0 10px 26px rgba(0,26,71,.14);
}

.rating-stars {
    color:#ffc247;
    font-size:.8rem;
}

.phone-frame {
    padding:.8rem;
    min-height:236px;
}

.phone-screen {
    min-height:214px;
    border-radius:8px;
    padding:.85rem;
    background:linear-gradient(180deg, #001a47, #0d56ad);
    color:#fff;
}

.mini-card {
    display:flex;
    align-items:center;
    gap:.7rem;
    padding:.65rem;
    margin-top:.7rem;
    border-radius:8px;
    color:var(--ink-color);
    background:#fff;
}

.mini-icon {
    width:36px;
    height:36px;
    display:grid;
    place-items:center;
    border-radius:8px;
    color:#fff;
    background:#00a6c8;
}

.partner-strip {
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:1rem;
    margin-top:1.3rem;
}

.partner-badge {
    display:flex;
    align-items:center;
    gap:.65rem;
    padding:.65rem .8rem;
    border-radius:8px;
    background:#fff;
    border:1px solid rgba(0,26,71,.1);
    box-shadow:0 10px 24px rgba(0,26,71,.06);
    font-size:.85rem;
    font-weight:700;
    color:var(--primary-color);
}

.partner-badge img {
    width:34px;
    height:34px;
    object-fit:contain;
}

.quick-row {
    display:grid;
    grid-template-columns:repeat(3, 1fr);
    gap:.75rem;
    margin-top:.9rem;
}

.quick-metric {
    min-height:86px;
    padding:.9rem;
    border-radius:8px;
    background:#f7fbff;
    border:1px solid rgba(0,26,71,.08);
}

.quick-metric strong {
    display:block;
    color:var(--primary-color);
    font-size:1.45rem;
    line-height:1;
}

.quick-metric span {
    color:var(--muted-color);
    font-size:.78rem;
    line-height:1.35;
}

section {
    padding:88px 0;
}

.section-kicker {
    color:var(--primary-soft);
    font-size:.82rem;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.08em;
    margin-bottom:.55rem;
}

.section-title {
    color:var(--primary-color);
    font-size:clamp(1.8rem, 3vw, 3rem);
    font-weight:800;
    letter-spacing:0;
    margin-bottom:.8rem;
}

.section-lead {
    color:var(--muted-color);
    line-height:1.75;
    max-width:760px;
}

.info-card {
    height:100%;
    background:var(--surface-color);
    border:1px solid var(--line-color);
    border-radius:8px;
    padding:1.45rem;
    box-shadow:0 12px 28px rgba(0,26,71,.06);
    transition:transform .2s ease, box-shadow .2s ease;
}

.info-card:hover {
    transform:translateY(-4px);
    box-shadow:0 20px 42px rgba(0,26,71,.12);
}

.icon-box {
    width:46px;
    height:46px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:8px;
    color:#fff;
    background:linear-gradient(135deg, var(--primary-color), var(--accent-color));
    margin-bottom:1rem;
}

.info-card h3 {
    color:var(--primary-color);
    font-size:1.06rem;
    font-weight:800;
}

.info-card p,
.info-card li {
    color:var(--muted-color);
    font-size:.94rem;
    line-height:1.7;
}

.support-band {
    color:#fff;
    background:
        linear-gradient(135deg, rgba(0,26,71,.96), rgba(0,75,155,.9)),
        var(--primary-color);
}

.support-band .section-kicker,
.support-band .section-title,
.support-band .section-lead {
    color:#fff;
}

.support-card {
    height:100%;
    padding:1.35rem;
    border-radius:8px;
    background:rgba(255,255,255,.1);
    border:1px solid rgba(255,255,255,.16);
}

.support-card i {
    width:44px;
    height:44px;
    display:grid;
    place-items:center;
    margin-bottom:1rem;
    border-radius:8px;
    color:var(--primary-color);
    background:#fff;
}

.support-card h3 {
    font-size:1.05rem;
    font-weight:800;
}

.support-card p {
    margin:0;
    color:rgba(255,255,255,.78);
    line-height:1.7;
    font-size:.94rem;
}

.about-band {
    background:#fff;
}

.feature-list {
    display:grid;
    gap:.85rem;
    margin-top:1.25rem;
}

.feature-item {
    display:flex;
    align-items:flex-start;
    gap:.8rem;
    padding:.9rem;
    border:1px solid rgba(0,26,71,.09);
    border-radius:8px;
    background:#f8fbff;
}

.feature-item i {
    color:var(--accent-color);
    margin-top:.18rem;
}

.showcase {
    border-radius:8px;
    overflow:hidden;
    box-shadow:var(--shadow);
    border:1px solid rgba(0,26,71,.08);
    background:#fff;
}

.showcase-top {
    padding:1.2rem;
    background:linear-gradient(135deg, var(--primary-color), #0c58b0);
    color:#fff;
}

.showcase-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:1px;
    background:rgba(0,26,71,.1);
}

.showcase-cell {
    min-height:128px;
    padding:1.1rem;
    background:#fff;
}

.showcase-cell span {
    color:var(--muted-color);
    font-size:.82rem;
}

.showcase-cell strong {
    display:block;
    color:var(--primary-color);
    font-size:1.05rem;
    margin-top:.35rem;
}

.steps {
    counter-reset:step;
}

.step-card {
    position:relative;
    padding:1.5rem 1.5rem 1.5rem 4.6rem;
}

.step-card::before {
    counter-increment:step;
    content:counter(step);
    position:absolute;
    left:1.45rem;
    top:1.45rem;
    width:38px;
    height:38px;
    display:grid;
    place-items:center;
    border-radius:8px;
    color:#fff;
    background:var(--primary-color);
    font-weight:800;
}

.directory-tabs {
    display:flex;
    flex-wrap:wrap;
    gap:.7rem;
    margin-bottom:1.2rem;
}

.tab-button {
    border:1px solid var(--line-color);
    background:#fff;
    color:#23415f;
    border-radius:8px;
    padding:.72rem 1rem;
    font-weight:700;
}

.tab-button.active {
    color:#fff;
    background:var(--primary-color);
    border-color:var(--primary-color);
}

.tab-panel {
    display:none;
}

.tab-panel.active {
    display:block;
}

.faq-wrap .accordion-item {
    border:1px solid var(--line-color);
    border-radius:8px;
    overflow:hidden;
    margin-bottom:.85rem;
    box-shadow:0 10px 24px rgba(0,26,71,.05);
}

.accordion-button {
    font-weight:800;
    color:var(--primary-color);
}

.accordion-button:not(.collapsed) {
    color:#fff;
    background:linear-gradient(135deg, var(--primary-color), #0d56ad);
}

.accordion-button:focus {
    box-shadow:0 0 0 .2rem rgba(0,184,217,.18);
}

.contact-section {
    background:linear-gradient(135deg, #f8fbff, #eef6ff);
}

.contact-card {
    background:#fff;
    border:1px solid var(--line-color);
    border-radius:8px;
    padding:1.5rem;
    box-shadow:var(--shadow);
}

.form-control,
.form-select {
    min-height:48px;
    border-radius:8px;
    border:1px solid rgba(0,26,71,.16);
}

.form-control:focus,
.form-select:focus {
    border-color:var(--accent-color);
    box-shadow:0 0 0 .2rem rgba(0,184,217,.14);
}

.contact-line {
    display:flex;
    align-items:flex-start;
    gap:.75rem;
    padding:.85rem 0;
    border-bottom:1px solid rgba(0,26,71,.08);
}

.contact-line:last-child {
    border-bottom:0;
}

.contact-line i {
    color:var(--accent-color);
    width:22px;
    margin-top:.18rem;
}

.footer {
    color:rgba(255,255,255,.78);
    background:var(--primary-color);
    padding:1.35rem 0;
    text-align:center;
}

.footer a {
    color:#fff;
}

.reveal {
    opacity:0;
    transform:translateY(18px);
    transition:opacity .6s ease, transform .6s ease;
}

.reveal.visible {
    opacity:1;
    transform:translateY(0);
}

.toast-message {
    position:fixed;
    right:18px;
    bottom:18px;
    z-index:2000;
    max-width:360px;
    display:none;
    padding:1rem 1.1rem;
    color:#fff;
    background:var(--primary-color);
    border-radius:8px;
    box-shadow:var(--shadow);
}

.toast-message.show {
    display:block;
}

@media (max-width:991px) {
    .navbar-collapse {
        padding:1rem 0;
    }

    .hero {
        min-height:auto;
        padding-top:112px;
    }

    .hero-panel {
        margin-top:2rem;
    }

    .online-hero-art {
        margin-top:2rem;
    }

}

@media (max-width:767px) {
    section {
        padding:64px 0;
    }

    .hero {
        padding-bottom:58px;
    }

    .quick-row,
    .showcase-grid,
    .app-preview {
        grid-template-columns:1fr;
    }

    .brand-lockup img {
        width:118px;
    }

    .online-hero-art {
        min-height:360px;
    }

    .floating-ui {
        font-size:.66rem;
        padding:.55rem .62rem;
    }

    .hero-laptop {
        left:7%;
        right:7%;
        width:auto;
    }

    .online-person {
        width:98px;
        height:166px;
    }

    .person-business {
        right:31%;
    }

    .step-card {
        padding-left:1.35rem;
        padding-top:4.5rem;
    }

    .step-card::before {
        left:1.35rem;
    }
}
</style>
</head>

<body>
<header class="site-header">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="brand-lockup" href="#home" aria-label="NasugView home">
                <img src="assets/nasugviewlogoblue.png" alt="NasugView logo">
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <li class="nav-item"><a class="nav-link active" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#support">Support</a></li>
                    <li class="nav-item"><a class="nav-link" href="#directory">Information</a></li>
                    <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                    <li class="nav-item ms-lg-2"><a class="btn-main" href="index.php"><i class="fa-solid fa-right-to-bracket"></i> Login</a></li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<main>
    <section class="hero" id="home">
        <div class="floating-circle circle-one" aria-hidden="true"></div>
        <div class="floating-circle circle-two" aria-hidden="true"></div>
        <div class="floating-circle circle-three" aria-hidden="true"></div>
        <div class="floating-circle circle-four" aria-hidden="true"></div>
        <div class="water-wave wave-a" aria-hidden="true"></div>
        <div class="water-wave wave-b" aria-hidden="true"></div>
        <div class="water-wave wave-c" aria-hidden="true"></div>
        <div class="container hero-content">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 reveal">
                    <span class="eyebrow"><i class="fa-solid fa-store"></i> Nasugbu business and community platform</span>
                    <h1>Discover businesses in one digital hub.</h1>
                    <p>
                        NasugView helps local businesses showcase products and services, helps customers and tourists find trusted establishments, and gives DTI tools to support MSME growth in Nasugbu, Batangas.
                    </p>
                    <div class="brand-tagline" aria-label="Discover Connect Support">
                        <span>Discover</span>
                        <span class="star">★</span>
                        <span>Connect</span>
                        <span class="star">★</span>
                        <span>Support</span>
                    </div>
                    <span class="brand-tagline-sub">Thrive with NasugView.</span>
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <a class="btn-main" href="#about"><i class="fa-solid fa-compass"></i> Explore NasugView</a>
                        <a class="btn-soft" href="#contact"><i class="fa-solid fa-paper-plane"></i> Get in touch</a>
                    </div>
                </div>

                <div class="col-lg-6 reveal">
                    <div class="online-hero-art" role="img" aria-label="Animated illustration of local businesses and consumers connected online">
                        <div class="digital-orbit" aria-hidden="true">
                            <div class="digital-line line-one"></div>
                            <div class="digital-line line-two"></div>
                            <div class="digital-line line-three"></div>
                            <span class="digital-node node-a"></span>
                            <span class="digital-node node-b"></span>
                            <span class="digital-node node-c"></span>
                        </div>

                        <div class="floating-ui ui-store"><i class="fa-solid fa-store"></i> Business profile</div>
                        <div class="floating-ui ui-review"><i class="fa-solid fa-star"></i> Reviews</div>
                        <div class="floating-ui ui-chat"><i class="fa-solid fa-comments"></i> Online inquiry</div>

                        <div class="hero-laptop" aria-hidden="true"></div>
                        <div class="screen-card" aria-hidden="true"></div>
                        <div class="online-person person-business" aria-hidden="true"></div>
                        <div class="online-person person-consumer" aria-hidden="true"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="about-band" id="about">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 reveal">
                    <div class="section-kicker">About NasugView</div>
                    <h2 class="section-title">What is NasugView?</h2>
                    <p class="section-lead">
                        NasugView: A Community Platform for Business Visibility and Growth in Nasugbu, Batangas is a digital system designed to transform local commerce in Nasugbu. It provides MSMEs with essential tools for online presence, inventory, and sales, while offering consumers an easy way to discover and engage with local businesses.
                    </p>
                    <button class="btn-main mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#aboutMore" aria-expanded="false" aria-controls="aboutMore">
                        <i class="fa-solid fa-book-open"></i> Read more
                    </button>
                    <div class="collapse" id="aboutMore">
                        <div class="feature-list mt-0">
                            <div class="feature-item">
                                <i class="fa-solid fa-circle-check"></i>
                                <div><strong>For MSMEs</strong><br><span class="text-secondary">Business owners can strengthen their digital presence by managing business details, products, inventory, sales information, and customer engagement in one platform.</span></div>
                            </div>
                            <div class="feature-item">
                                <i class="fa-solid fa-circle-check"></i>
                                <div><strong>For consumers</strong><br><span class="text-secondary">Customers can search for local establishments, explore products and services, check locations, and interact with businesses more conveniently.</span></div>
                            </div>
                            <div class="feature-item">
                                <i class="fa-solid fa-circle-check"></i>
                                <div><strong>For local growth</strong><br><span class="text-secondary">NasugView supports business visibility, customer trust, and community-based commerce for the growth of MSMEs in Nasugbu, Batangas.</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="partner-strip">
                        <div class="partner-badge"><img src="assets/dti-philippines.png" alt="DTI Philippines"> DTI support</div>
                        <div class="partner-badge"><img src="assets/negosyo-center.png" alt="Negosyo Center"> Negosyo Center</div>
                    </div>
                </div>

                <div class="col-lg-6 reveal">
                    <div class="showcase">
                        <div class="showcase-top">
                            <div class="d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <h3 class="h5 fw-bold mb-1">Community platform overview</h3>
                                    <p class="mb-0 opacity-75">Built for discovery, promotion, engagement, and growth.</p>
                                </div>
                                <i class="fa-solid fa-chart-line fa-2x opacity-75"></i>
                            </div>
                        </div>
                        <div class="showcase-grid">
                            <div class="showcase-cell">
                                <span>Listings</span>
                                <strong>Business profiles, products, and services</strong>
                            </div>
                            <div class="showcase-cell">
                                <span>Map</span>
                                <strong>Location-based business discovery</strong>
                            </div>
                            <div class="showcase-cell">
                                <span>Community</span>
                                <strong>Ratings, reviews, and customer feedback</strong>
                            </div>
                            <div class="showcase-cell">
                                <span>Growth</span>
                                <strong>More reach for businesses, easier shopping for customers</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="features">
        <div class="container">
            <div class="text-center mx-auto mb-5 reveal" style="max-width:790px;">
                <div class="section-kicker">Core features</div>
                <h2 class="section-title">Use your mobile devices to experience these features.</h2>
                <p class="section-lead mx-auto">NasugView gives verified MSMEs and consumers practical mobile tools for trusted discovery, navigation, and community feedback.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4 reveal">
                    <article class="info-card">
                        <div class="icon-box"><i class="fa-solid fa-user-shield"></i></div>
                        <h3>Account and Validation</h3>
                        <p>Ensures only legitimate MSMEs register by requiring business permits and using an official list for account verification and security.</p>
                    </article>
                </div>
                <div class="col-md-6 col-lg-4 reveal">
                    <article class="info-card">
                        <div class="icon-box"><i class="fa-solid fa-map-location-dot"></i></div>
                        <h3>Business Listing and Map</h3>
                        <p>Serves as a searchable directory, allowing consumers to browse businesses by category or location for easy navigation and discovery.</p>
                    </article>
                </div>
                <div class="col-md-6 col-lg-4 reveal">
                    <article class="info-card">
                        <div class="icon-box"><i class="fa-solid fa-star-half-stroke"></i></div>
                        <h3>Consumer Review and Rating</h3>
                        <p>Builds trust and credibility by allowing consumers to leave honest ratings and feedback, helping potential customers make informed decisions and encouraging business improvement.</p>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <section class="support-band" id="support">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-5 reveal">
                    <div class="section-kicker">Business and shopping support</div>
                    <h2 class="section-title">More than a directory, NasugView helps local businesses get discovered.</h2>
                    <p class="section-lead">
                        NasugView gives local entrepreneurs a digital space to promote their products, share updates, and reach more customers. For shoppers and tourists, it makes finding trusted stores, services, and local offers around Nasugbu easier and more convenient.
                    </p>
                    <div class="partner-strip">
                        <div class="partner-badge"><img src="assets/dti-philippines.png" alt="DTI Philippines"> Department of Trade and Industry</div>
                        <div class="partner-badge"><img src="assets/negosyo-center.png" alt="Negosyo Center"> Negosyo Center support</div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="row g-3">
                        <div class="col-md-6 reveal">
                            <article class="support-card">
                                <i class="fa-solid fa-chalkboard-user"></i>
                                <h3>Business learning opportunities</h3>
                                <p>Help business owners discover learning sessions, training schedules, and entrepreneurship activities that can improve daily operations.</p>
                            </article>
                        </div>
                        <div class="col-md-6 reveal">
                            <article class="support-card">
                                <i class="fa-solid fa-bullhorn"></i>
                                <h3>Store updates and offers</h3>
                                <p>Let businesses share important updates, product highlights, promos, and service announcements in one accessible place.</p>
                            </article>
                        </div>
                        <div class="col-md-6 reveal">
                            <article class="support-card">
                                <i class="fa-solid fa-eye"></i>
                                <h3>Business visibility</h3>
                                <p>Give MSMEs a stronger online presence through searchable listings, promotional content, reviews, and location-based discovery.</p>
                            </article>
                        </div>
                        <div class="col-md-6 reveal">
                            <article class="support-card">
                                <i class="fa-solid fa-chart-line"></i>
                                <h3>Customer reach</h3>
                                <p>Connect local sellers with shoppers who are already looking for nearby products, services, food spots, and trusted establishments.</p>
                            </article>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="about-band" id="directory">
        <div class="container">
            <div class="row g-5 align-items-start">
                <div class="col-lg-5 reveal">
                    <div class="section-kicker">Other information</div>
                    <h2 class="section-title">Built for every group in the local business ecosystem.</h2>
                    <p class="section-lead">Use the quick tabs to see how NasugView helps business owners, shoppers, tourists, and the local community.</p>
                    <div class="directory-tabs" role="tablist" aria-label="NasugView information tabs">
                        <button class="tab-button active" type="button" data-tab="customers">Customers</button>
                        <button class="tab-button" type="button" data-tab="tourists">Tourists</button>
                        <button class="tab-button" type="button" data-tab="owners">Business Owners</button>
                        <button class="tab-button" type="button" data-tab="community">Community</button>
                    </div>
                </div>

                <div class="col-lg-7 reveal">
                    <div class="tab-panel active" id="tab-customers">
                        <div class="info-card">
                            <h3><i class="fa-solid fa-magnifying-glass-location me-2 text-primary"></i>For customers</h3>
                            <p>Customers can search for products and services, compare business profiles, check reviews, and find establishments that match what they need.</p>
                            <ul class="mb-0">
                                <li>Search by product, service, category, or location</li>
                                <li>Use ratings and reviews to guide decisions</li>
                                <li>Receive helpful suggestions for relevant businesses</li>
                            </ul>
                        </div>
                    </div>
                    <div class="tab-panel" id="tab-tourists">
                        <div class="info-card">
                            <h3><i class="fa-solid fa-umbrella-beach me-2 text-primary"></i>For tourists</h3>
                            <p>Tourists can discover local shops, food spots, services, and destinations while navigating Nasugbu with map-based information.</p>
                            <ul class="mb-0">
                                <li>Explore businesses near tourist areas</li>
                                <li>Locate establishments through integrated maps</li>
                                <li>Find authentic local products and services</li>
                            </ul>
                        </div>
                    </div>
                    <div class="tab-panel" id="tab-owners">
                        <div class="info-card">
                            <h3><i class="fa-solid fa-store me-2 text-primary"></i>For business owners</h3>
                            <p>Business owners can improve online presence by maintaining profiles, uploading promotional content, and engaging with customer feedback.</p>
                            <ul class="mb-0">
                                <li>Showcase products, services, images, and announcements</li>
                                <li>Build trust through customer reviews and ratings</li>
                                <li>Join training programs and DTI-supported activities</li>
                            </ul>
                        </div>
                    </div>
                    <div class="tab-panel" id="tab-community">
                        <div class="info-card">
                            <h3><i class="fa-solid fa-people-group me-2 text-primary"></i>For the local community</h3>
                            <p>NasugView encourages people to support local MSMEs by making nearby businesses easier to find, compare, visit, and recommend.</p>
                            <ul class="mb-0">
                                <li>Support homegrown products and services</li>
                                <li>Discover trusted shops, restaurants, and service providers</li>
                                <li>Help businesses grow through ratings, reviews, and visits</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-4 steps">
                <div class="col-md-4 reveal">
                    <article class="info-card step-card">
                        <h3>Discover</h3>
                        <p class="mb-0">Customers and tourists explore MSMEs, products, services, and nearby establishments around Nasugbu.</p>
                    </article>
                </div>
                <div class="col-md-4 reveal">
                    <article class="info-card step-card">
                        <h3>Engage</h3>
                        <p class="mb-0">Users leave ratings and reviews while business owners promote offers and communicate value.</p>
                    </article>
                </div>
                <div class="col-md-4 reveal">
                    <article class="info-card step-card">
                        <h3>Grow</h3>
                        <p class="mb-0">Local businesses gain visibility, stronger customer trust, and more chances to turn online discovery into real visits and sales.</p>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <section id="faq">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-5 reveal">
                    <div class="section-kicker">FAQ</div>
                    <h2 class="section-title">Common questions about NasugView.</h2>
                    <p class="section-lead">These answers help customers, tourists, and entrepreneurs understand how the platform works.</p>
                </div>
                <div class="col-lg-7 reveal">
                    <div class="accordion faq-wrap" id="faqAccordion">
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faqOne" aria-expanded="true" aria-controls="faqOne">What is NasugView?</button>
                            </h3>
                            <div id="faqOne" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">NasugView is a web and mobile community platform for promoting local businesses, helping users discover products and services, and supporting DTI programs for MSME growth in Nasugbu, Batangas.</div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqTwo" aria-expanded="false" aria-controls="faqTwo">Who can use NasugView?</button>
                            </h3>
                            <div id="faqTwo" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">Business owners can showcase their enterprises, while customers and tourists can discover, compare, visit, rate, and review local establishments around Nasugbu.</div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqThree" aria-expanded="false" aria-controls="faqThree">How does NasugView help users discover businesses?</button>
                            </h3>
                            <div id="faqThree" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">The platform organizes listings by business details, products, services, reviews, and location so customers and tourists can quickly find establishments that match their needs.</div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqFour" aria-expanded="false" aria-controls="faqFour">How does NasugView help local economic growth?</button>
                            </h3>
                            <div id="faqFour" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">It improves digital visibility for MSMEs, encourages customer feedback, supports tourism discovery, and helps more people choose local products and services.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="contact-section" id="contact">
        <div class="container">
            <div class="row g-5 align-items-stretch">
                <div class="col-lg-5 reveal">
                    <div class="section-kicker">Contact</div>
                    <h2 class="section-title">Talk to the NasugView team.</h2>
                    <p class="section-lead">For business listing concerns, promotional content, customer support, DTI training, webinars, or platform access, send a message and the team can follow up.</p>
                    <div class="contact-card mt-4">
                        <div class="contact-line">
                            <i class="fa-solid fa-location-dot"></i>
                            <div><strong>Office</strong><br><span class="text-secondary">Nasugbu, Batangas business support and DTI coordination</span></div>
                        </div>
                        <div class="contact-line">
                            <i class="fa-solid fa-envelope"></i>
                            <div><strong>Email</strong><br><span class="text-secondary">nasugview.support@example.com</span></div>
                        </div>
                        <div class="contact-line">
                            <i class="fa-solid fa-clock"></i>
                            <div><strong>Office hours</strong><br><span class="text-secondary">Monday to Friday, 8:00 AM to 5:00 PM</span></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 reveal">
                    <form class="contact-card h-100" id="contactForm" novalidate>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="name">Full name</label>
                                <input class="form-control" type="text" id="name" placeholder="Your name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="email">Email address</label>
                                <input class="form-control" type="email" id="email" placeholder="name@example.com" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="topic">Topic</label>
                                <select class="form-select" id="topic" required>
                                    <option value="">Select topic</option>
                                    <option>Account access</option>
                                    <option>Business listing</option>
                                    <option>Products and services</option>
                                    <option>Webinars and training</option>
                                    <option>Ratings, reviews, and maps</option>
                                    <option>General inquiry</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="phone">Phone number</label>
                                <input class="form-control" type="tel" id="phone" placeholder="Optional">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="message">Message</label>
                                <textarea class="form-control" id="message" rows="5" placeholder="How can we help?" required></textarea>
                            </div>
                            <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <small class="text-secondary">This demo form validates your message before sending.</small>
                                <button class="btn-main" type="submit"><i class="fa-solid fa-paper-plane"></i> Send message</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>

<footer class="footer">
    <div class="container">
        <span>&copy; 2026 NasugView. All rights reserved.</span>
    </div>
</footer>

<div class="toast-message" id="toastMessage" role="status" aria-live="polite"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const navLinks = document.querySelectorAll('.nav-link[href^="#"]');
const sections = [...navLinks].map(link => document.querySelector(link.getAttribute('href'))).filter(Boolean);
const navbarCollapse = document.getElementById('mainNav');

navLinks.forEach(link => {
    link.addEventListener('click', () => {
        if (navbarCollapse.classList.contains('show')) {
            bootstrap.Collapse.getOrCreateInstance(navbarCollapse).hide();
        }
    });
});

const setActiveLink = () => {
    let current = sections[0]?.id || 'home';
    sections.forEach(section => {
        const sectionTop = section.offsetTop - 120;
        if (window.scrollY >= sectionTop) {
            current = section.id;
        }
    });

    navLinks.forEach(link => {
        link.classList.toggle('active', link.getAttribute('href') === `#${current}`);
    });
};

window.addEventListener('scroll', setActiveLink);
setActiveLink();

const revealObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            revealObserver.unobserve(entry.target);
        }
    });
}, { threshold:.14 });

document.querySelectorAll('.reveal').forEach(element => revealObserver.observe(element));

const counters = document.querySelectorAll('[data-count]');
let countersStarted = false;
const countObserver = new IntersectionObserver(entries => {
    if (!entries.some(entry => entry.isIntersecting) || countersStarted) return;
    countersStarted = true;

    counters.forEach(counter => {
        const target = Number(counter.dataset.count);
        const duration = 900;
        const start = performance.now();

        const update = now => {
            const progress = Math.min((now - start) / duration, 1);
            counter.textContent = Math.round(progress * target);
            if (progress < 1) requestAnimationFrame(update);
        };

        requestAnimationFrame(update);
    });
}, { threshold:.4 });

const heroPanel = document.querySelector('.hero-panel');
if (heroPanel) countObserver.observe(heroPanel);

document.querySelectorAll('.tab-button').forEach(button => {
    button.addEventListener('click', () => {
        const target = button.dataset.tab;

        document.querySelectorAll('.tab-button').forEach(item => {
            item.classList.toggle('active', item === button);
        });

        document.querySelectorAll('.tab-panel').forEach(panel => {
            panel.classList.toggle('active', panel.id === `tab-${target}`);
        });
    });
});

const toast = document.getElementById('toastMessage');
const showToast = message => {
    toast.textContent = message;
    toast.classList.add('show');
    window.setTimeout(() => toast.classList.remove('show'), 3600);
};

document.getElementById('contactForm').addEventListener('submit', event => {
    event.preventDefault();
    const form = event.currentTarget;

    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        showToast('Please complete the required fields before sending.');
        return;
    }

    form.reset();
    form.classList.remove('was-validated');
    showToast('Message ready. Connect this form to your email or database when needed.');
});
</script>
</body>
</html>
