<?php
// Hospital Management System - Login Page (Landing)
session_start();

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard');
    exit;
}

$error = '';
$success = '';

if (isset($_GET['logged_out']) && $_GET['logged_out'] == 1) {
    $success = 'You have been successfully logged out.';
}
if (isset($_GET['timeout']) && $_GET['timeout'] == 1) {
    $error = 'Your session has expired. Please login again.';
}

$is_locked = false;
$remaining_minutes = 0;
if (isset($_SESSION['login_locked_until']) && time() < $_SESSION['login_locked_until']) {
    $is_locked = true;
    $remaining_minutes = ceil(($_SESSION['login_locked_until'] - time()) / 60);
    $error = "Too many failed attempts. Please try again in {$remaining_minutes} minutes.";
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid':  $error = 'Invalid username or password.'; break;
        case 'inactive': $error = 'Your account is inactive. Please contact administrator.'; break;
        case 'csrf':     $error = 'Security validation failed. Please try again.'; break;
    }
}

$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#9E2A1E">
    <meta name="description" content="SIWOT Hospital Management System — Secure healthcare administration platform.">
    <title>SIWOT HMS | Hospital Management System</title>

    <!-- Inter — same as original -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">

    <style>
        :root {
            --brand:      #9E2A1E;
            --brand-dk:   #7A1F16;
            --brand-lt:   #B53B2E;
            --brand-glow: rgba(158,42,30,.18);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            min-height: 100vh;
        }

        /* ─── FULL-PAGE BACKGROUND ───────────────────── */
        .page {
            position: relative;
            width: 100%;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        /* Hero image */
        .page__bg {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center 25%;
            z-index: 0;
        }

        /* Dark + brand scrim */
        .page__scrim {
            position: fixed;
            inset: 0;
            z-index: 1;
            background: linear-gradient(135deg, rgba(107,27,18,.45) 0%, rgba(26,10,7,.35) 100%);
        }

        /* Subtle dot grid */
        .page__dots {
            position: fixed;
            inset: 0;
            z-index: 2;
            background-image: radial-gradient(circle, rgba(255,255,255,.06) 1px, transparent 1px);
            background-size: 32px 32px;
            pointer-events: none;
        }

        /* ─── STATUS CHIP (top-right) ────────────────── */
        .status-chip {
            position: fixed;
            top: 1.5rem;
            right: 1.75rem;
            z-index: 20;
            display: flex;
            align-items: center;
            gap: .6rem;
            background: rgba(255,255,255,.08);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 100px;
            padding: .45rem 1rem .45rem .6rem;
            animation: fadeDown .6s .6s ease both;
        }

        .status-chip__dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #4ADE80;
            box-shadow: 0 0 0 3px rgba(74,222,128,.25);
            animation: pulse 2.5s ease infinite;
            flex-shrink: 0;
        }

        .status-chip__text {
            font-size: .78rem;
            font-weight: 600;
            color: #fff;
            letter-spacing: .01em;
        }

        .status-chip__sub {
            font-size: .72rem;
            color: rgba(255,255,255,.55);
            margin-left: .2rem;
        }

        /* ─── CENTERED CARD ──────────────────────────── */
        .card-wrap {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 460px;
            animation: fadeUp .65s ease both;
        }

        .card {
            background: rgba(255,255,255,.97);
            border-radius: 20px;
            box-shadow:
                0 32px 64px rgba(0,0,0,.35),
                0 0 0 1px rgba(255,255,255,.15);
            overflow: hidden;
        }

        /* Crimson top bar */
        .card__topbar {
            height: 4px;
            background: linear-gradient(90deg, var(--brand-dk), var(--brand), var(--brand-lt));
        }

        .card__body {
            padding: 2.5rem 2.5rem 2rem;
        }

        /* ─── CARD HEADER ────────────────────────────── */
        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #F0EDE9;
        }

        .card-header__icon {
            width: 3rem;
            height: 3rem;
            border-radius: 12px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 12px var(--brand-glow);
            overflow: hidden;
        }
        
        .card-header__icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .card-header__title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #111827;
            letter-spacing: -.02em;
            line-height: 1.2;
        }

        .card-header__sub {
            font-size: .82rem;
            color: #6B7280;
            margin-top: .2rem;
            font-weight: 400;
        }

        /* ─── ALERTS ─────────────────────────────────── */
        .alert {
            border-radius: 10px;
            padding: .875rem 1rem;
            margin-bottom: 1.4rem;
            display: flex;
            align-items: flex-start;
            gap: .7rem;
            font-size: .855rem;
            animation: fadeUp .3s ease;
        }

        .alert-error   { background: #FFF1F1; border: 1px solid #FECACA; color: #B91C1C; }
        .alert-success { background: #F0FDF4; border: 1px solid #BBF7D0; color: #15803D; }
        .alert i { flex-shrink: 0; margin-top: .15rem; }

        /* ─── FIELDS ─────────────────────────────────── */
        .field { margin-bottom: 1.1rem; }

        .field__label {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: .45rem;
            letter-spacing: .01em;
        }

        .field__row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: .45rem;
        }

        .field__row .field__label { margin-bottom: 0; }

        .field__row a {
            font-size: .78rem;
            color: var(--brand);
            text-decoration: none;
            font-weight: 500;
            transition: opacity .2s;
        }

        .field__row a:hover { opacity: .7; }

        .input-wrap { position: relative; }

        .input-wrap .ico {
            position: absolute;
            left: .95rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: .82rem;
            pointer-events: none;
            transition: color .25s;
        }

        input.inp {
            width: 100%;
            height: 2.9rem;
            padding: 0 2.75rem 0 2.6rem;
            border: 1.5px solid #E5E7EB;
            border-radius: 10px;
            background: #F9FAFB;
            font-family: 'Inter', sans-serif;
            font-size: .9rem;
            color: #111827;
            outline: none;
            transition: border .25s, background .25s, box-shadow .25s;
        }

        input.inp::placeholder { color: #9CA3AF; }
        input.inp:hover  { border-color: #D1D5DB; }

        input.inp:focus  {
            background: #fff;
            border-color: var(--brand);
            box-shadow: 0 0 0 3.5px var(--brand-glow);
        }

        input.inp:focus ~ .ico,
        input.inp:not(:placeholder-shown) ~ .ico { color: var(--brand); }

        input.inp:disabled { opacity: .5; cursor: not-allowed; }

        /* Password toggle */
        .pwd-btn {
            position: absolute;
            right: .85rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #9CA3AF;
            font-size: .82rem;
            padding: .2rem;
            transition: color .2s;
        }

        .pwd-btn:hover { color: var(--brand); }

        /* ─── REMEMBER ROW ───────────────────────────── */
        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.4rem;
        }

        .remember-row label {
            display: flex;
            align-items: center;
            gap: .5rem;
            cursor: pointer;
            font-size: .855rem;
            color: #4B5563;
            user-select: none;
        }

        input[type="checkbox"].chk {
            -webkit-appearance: none;
            appearance: none;
            width: 1.05rem;
            height: 1.05rem;
            border: 1.5px solid #D1D5DB;
            border-radius: 4px;
            background: #fff;
            cursor: pointer;
            position: relative;
            transition: all .2s;
            flex-shrink: 0;
        }

        input[type="checkbox"].chk:checked {
            background: var(--brand);
            border-color: var(--brand);
        }

        input[type="checkbox"].chk:checked::after {
            content: '';
            position: absolute;
            top: 1px; left: 4px;
            width: 4px; height: 7px;
            border: 2px solid #fff;
            border-top: none; border-left: none;
            transform: rotate(40deg);
        }

        .locked-badge {
            font-size: .78rem;
            font-weight: 600;
            color: #B91C1C;
            display: flex;
            align-items: center;
            gap: .3rem;
        }

        /* ─── SUBMIT BUTTON ──────────────────────────── */
        .btn-submit {
            width: 100%;
            height: 2.9rem;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--brand-dk) 0%, var(--brand-lt) 100%);
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: .925rem;
            font-weight: 600;
            letter-spacing: .01em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            box-shadow: 0 4px 14px rgba(158,42,30,.3), inset 0 1px 0 rgba(255,255,255,.12);
            transition: transform .2s, box-shadow .2s, opacity .2s;
            margin-bottom: 1.5rem;
        }

        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(158,42,30,.42), inset 0 1px 0 rgba(255,255,255,.12);
        }

        .btn-submit:active:not(:disabled) { transform: translateY(0); }
        .btn-submit:disabled { opacity: .65; cursor: not-allowed; }

        .btn-submit .spin {
            display: none;
            width: 1.1rem; height: 1.1rem;
            border: 2px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin .75s linear infinite;
        }

        .btn-submit.loading .btn-label { display: none; }
        .btn-submit.loading .spin      { display: block; }

        /* ─── SECURITY BADGES ────────────────────────── */
        .sec-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.25rem;
            padding-top: 1.25rem;
            border-top: 1px solid #F3F4F6;
        }

        .sec-item {
            display: flex;
            align-items: center;
            gap: .35rem;
            font-size: .73rem;
            color: #9CA3AF;
        }

        .sec-item i { color: var(--brand); font-size: .68rem; }

        /* ─── CARD FOOTER ────────────────────────────── */
        .card-footer {
            text-align: center;
            padding: 1rem 2.5rem 1.5rem;
            font-size: .72rem;
            color: #9CA3AF;
            border-top: 1px solid #F3F4F6;
            background: #FAFAFA;
        }

        /* ─── BOTTOM FEATURE PILLS ───────────────────── */
        .tagline {
            position: relative;
            z-index: 10;
            margin-top: 1.25rem;
            text-align: center;
            animation: fadeUp .65s .15s ease both;
        }

        .tagline__pills {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: .45rem;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: .38rem;
            padding: .35rem .85rem;
            border-radius: 100px;
            font-size: .77rem;
            font-weight: 500;
            color: rgba(255,255,255,.88);
            border: 1px solid rgba(255,255,255,.18);
            background: rgba(255,255,255,.08);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            letter-spacing: .01em;
        }

        .pill i { font-size: .7rem; opacity: .75; }

        /* ─── KEYFRAMES ──────────────────────────────── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeDown {
            from { opacity: 0; transform: translateY(-14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse {
            0%,100% { box-shadow: 0 0 0 3px rgba(74,222,128,.25); }
            50%      { box-shadow: 0 0 0 6px rgba(74,222,128,.1); }
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        *:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }

        /* ─── RESPONSIVE ─────────────────────────────── */
        @media (max-width: 520px) {
            .card__body { padding: 2rem 1.5rem 1.5rem; }
            .card-footer { padding: .875rem 1.5rem 1.25rem; }
            .status-chip { top: .85rem; right: .85rem; }
        }
    </style>
</head>
<body>

<div class="page">

    <!-- Background -->
    <img class="page__bg"
         src="https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=1920&q=90&auto=format&fit=crop"
         alt="Hospital">
    <div class="page__scrim"></div>
    <div class="page__dots"></div>

    <!-- Centered login card -->
    <div class="card-wrap">

        <div class="card">
            <div class="card__topbar"></div>

            <div class="card__body">

                <!-- Header -->
                <div class="card-header">
                    <div class="card-header__icon">
                        <img src="../assets/images/logo.jpeg" alt="SIWOT Hospital Logo">
                    </div>
                    <div>
                        <div class="card-header__title">SIWOT HMS</div>
                        <div class="card-header__sub">Sign in to access your dashboard</div>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong><?php echo htmlspecialchars($error); ?></strong>
                        <?php if ($is_locked): ?>
                        <div style="font-size:.8rem;margin-top:.2rem;opacity:.85;" id="countdown"><?php echo $remaining_minutes; ?>:00 remaining</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <strong><?php echo htmlspecialchars($success); ?></strong>
                </div>
                <?php endif; ?>

                <!-- Form -->
                <form action="process_login" method="POST" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <!-- Username -->
                    <div class="field">
                        <label class="field__label" for="username">Username</label>
                        <div class="input-wrap">
                            <input type="text"
                                   id="username"
                                   name="username"
                                   class="inp"
                                   placeholder="Enter your username"
                                   autocomplete="username"
                                   required
                                   <?php echo $is_locked ? 'disabled' : ''; ?>>
                            <i class="ico fas fa-user"></i>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="field">
                        <label class="field__label" for="password">Password</label>
                        <div class="input-wrap">
                            <input type="password"
                                   id="password"
                                   name="password"
                                   class="inp"
                                   placeholder="Enter your password"
                                   autocomplete="current-password"
                                   required
                                   <?php echo $is_locked ? 'disabled' : ''; ?>>
                            <i class="ico fas fa-lock"></i>
                            <button type="button" class="pwd-btn" onclick="togglePassword()" tabindex="-1">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Remember me -->
                    <div class="remember-row">
                        <label>
                            <input type="checkbox" name="remember" class="chk" <?php echo $is_locked ? 'disabled' : ''; ?>>
                            <span>Remember me</span>
                        </label>
                        <?php if ($is_locked): ?>
                        <span class="locked-badge"><i class="fas fa-lock"></i> Account locked</span>
                        <?php endif; ?>
                    </div>

                    <!-- Submit -->
                    <button type="submit" id="submitBtn" class="btn-submit" <?php echo $is_locked ? 'disabled' : ''; ?>>
                        <span class="btn-label">
                            <?php if ($is_locked): ?>
                                <i class="fas fa-lock"></i>&ensp;Account Locked
                            <?php else: ?>
                                Sign In&ensp;<i class="fas fa-arrow-right" style="font-size:.78rem;"></i>
                            <?php endif; ?>
                        </span>
                        <span class="spin"></span>
                    </button>
                </form>

            </div><!-- /card__body -->

            <div class="card-footer">
                &copy; <?php echo date('Y'); ?> SIWOT Hospital Management System. All rights reserved.
            </div>
        </div><!-- /card -->


    </div><!-- /card-wrap -->

</div><!-- /page -->

<script>
    function togglePassword() {
        const inp  = document.getElementById('password');
        const icon = document.getElementById('toggleIcon');
        const show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        icon.classList.toggle('fa-eye',       !show);
        icon.classList.toggle('fa-eye-slash',  show);
    }

    const form = document.getElementById('loginForm');
    const btn  = document.getElementById('submitBtn');

    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) { e.preventDefault(); return; }
        btn.disabled = true;
        btn.classList.add('loading');
    });

    window.addEventListener('DOMContentLoaded', function() {
        const f = document.getElementById('username');
        if (f && !f.disabled) setTimeout(() => f.focus(), 120);
    });

    <?php if ($is_locked && $remaining_minutes > 0): ?>
    let secs = <?php echo ($remaining_minutes * 60); ?>;
    (function tick() {
        const el = document.getElementById('countdown');
        if (!el) return;
        const m = Math.floor(secs / 60), s = secs % 60;
        el.textContent = `${m}:${String(s).padStart(2,'0')} remaining`;
        if (secs-- > 0) setTimeout(tick, 1000); else location.reload();
    })();
    <?php endif; ?>
</script>

</body>
</html>