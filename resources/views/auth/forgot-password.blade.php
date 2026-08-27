<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password — Financial Management System</title>
<link rel="stylesheet" href="{{ asset('app/css/style.css') }}">
<link rel="stylesheet" href="{{ asset('app/css/forms.css') }}">
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg);padding:24px;">
  <div class="card" style="max-width:420px;width:100%;padding:32px;">
    <h2 style="font-family:var(--font-display);font-size:18px;margin-bottom:8px;">Reset your password</h2>
    <p class="text-muted" style="font-size:13px;margin-bottom:20px;">Enter your account email and we will send you a link to reset your password. The link expires after 60 minutes and can only be used once.</p>

    @if (session('status'))
      <div style="background:var(--success-bg);color:var(--success);padding:10px 12px;border-radius:8px;font-size:12.5px;margin-bottom:16px;">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
      <div style="background:var(--danger-bg);color:var(--danger);padding:10px 12px;border-radius:8px;font-size:12.5px;margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
      @csrf
      <div class="form-field">
        <label>Email</label>
        <input type="email" name="email" required autofocus value="{{ old('email') }}">
      </div>
      <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
    </form>
    <p style="text-align:center;font-size:12px;margin-top:16px;"><a href="{{ route('login') }}" style="color:var(--teal-600);font-weight:600;">Back to sign in</a></p>
  </div>
</body>
</html>
