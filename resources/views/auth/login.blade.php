<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — Financial Management System Prototype</title>
  <link rel="stylesheet" href="{{ asset('app/css/style.css') }}">
  <style>
    body.login-body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: radial-gradient(1200px 700px at 15% -10%, #16324F 0%, #0B2036 55%, #081627 100%);
      padding: 32px 16px;
    }

    .login-shell {
      display: grid;
      grid-template-columns: 1.05fr 1fr;
      max-width: 980px;
      width: 100%;
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 30px 70px rgba(0, 0, 0, 0.35);
    }

    .login-brand {
      background: linear-gradient(165deg, #12293F, #0B2036);
      color: #E9EEF3;
      padding: 46px 40px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      overflow: hidden;
    }

    .login-brand::after {
      content: '';
      position: absolute;
      right: -60px;
      bottom: -60px;
      width: 220px;
      height: 220px;
      border-radius: 50%;
      border: 40px solid rgba(184, 134, 58, 0.14);
    }

    .login-brand .mark {
      font-family: var(--font-display);
      font-weight: 700;
      background: var(--gold-500);
      color: #0B2036;
      padding: 8px 11px;
      border-radius: 8px;
      display: inline-block;
      font-size: 13px;
      letter-spacing: 0.5px;
    }

    .login-brand h1 {
      color: #fff;
      font-size: 26px;
      line-height: 1.25;
      margin: 22px 0 12px;
      max-width: 320px;
    }

    .login-brand p {
      color: #A9BACB;
      font-size: 13.3px;
      line-height: 1.6;
      max-width: 300px;
    }

    .login-panel {
      background: #fff;
      padding: 44px 40px;
      display: flex;
      flex-direction: column;
    }

    .login-panel h2 {
      font-size: 19px;
      margin-bottom: 6px;
      font-family: var(--font-display);
      color: var(--ink-900);
    }

    .login-panel>p.lead {
      color: var(--text-muted);
      font-size: 13px;
      margin-bottom: 24px;
    }

    .login-field {
      margin-bottom: 16px;
    }

    .login-field label {
      display: block;
      font-size: 12.3px;
      font-weight: 600;
      color: var(--text);
      margin-bottom: 6px;
    }

    .login-field input[type=email],
    .login-field input[type=password] {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--line-strong);
      border-radius: 8px;
      font-size: 13.5px;
    }

    .login-remember {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12.5px;
      color: var(--text-muted);
      margin-bottom: 18px;
    }

    .login-error {
      background: var(--danger-bg);
      color: var(--danger);
      border-radius: 8px;
      padding: 10px 12px;
      font-size: 12.5px;
      margin-bottom: 16px;
    }

    .login-status {
      background: var(--success-bg);
      color: var(--success);
      border-radius: 8px;
      padding: 10px 12px;
      font-size: 12.5px;
      margin-bottom: 16px;
    }

    .login-foot {
      margin-top: 16px;
      font-size: 11.8px;
      color: var(--text-faint);
      text-align: center;
    }

    .login-foot a {
      color: var(--teal-600);
      font-weight: 600;
    }

    @media (max-width: 820px) {
      .login-shell {
        grid-template-columns: 1fr;
      }

      .login-brand {
        padding: 32px 28px;
      }
    }
  </style>
</head>

<body class="login-body">

  <div class="login-shell">
    <div class="login-brand">
      <div>
        <span class="mark">FMS</span>
        <h1>Financial Management System Prototype</h1>
        <p>Sign in with your Prototype account to access budgeting, revenue, expenses, payables, receivables, funds, procurement, and asset records.</p>
      </div>
    </div>

    <div class="login-panel">
      <h2>Sign in to your account</h2>
      <p class="lead">Enter the email and password issued to you by your Administrator.</p>

      @if (isset($errors) && $errors->any())
      <div class="login-error">{{ $errors->first() }}</div>
      @endif
      @if (session('status'))
      <div class="login-status">{{ session('status') }}</div>
      @endif

      <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="login-field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>
        <div class="login-field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required>
        </div>
        <label class="login-remember">
          <input type="checkbox" name="remember"> Remember me on this device
        </label>
        <button type="submit" class="btn btn-primary btn-block">Sign In</button>
      </form>

      <p class="login-foot"><a href="{{ route('password.request') }}">Forgot your password?</a></p>
    </div>
  </div>
</body>

</html>