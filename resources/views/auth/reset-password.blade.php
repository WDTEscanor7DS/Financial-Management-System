<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — Financial Management System</title>
<link rel="stylesheet" href="{{ asset('app/css/style.css') }}">
<link rel="stylesheet" href="{{ asset('app/css/forms.css') }}">
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg);padding:24px;">
  <div class="card" style="max-width:420px;width:100%;padding:32px;">
    <h2 style="font-family:var(--font-display);font-size:18px;margin-bottom:8px;">Choose a new password</h2>
    <p class="text-muted" style="font-size:13px;margin-bottom:20px;">Must be at least 10 characters and include upper/lowercase letters and a number.</p>

    @if ($errors->any())
      <div style="background:var(--danger-bg);color:var(--danger);padding:10px 12px;border-radius:8px;font-size:12.5px;margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('password.update') }}">
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">
      <div class="form-field">
        <label>Email</label>
        <input type="email" name="email" required value="{{ $email ?? old('email') }}">
      </div>
      <div class="form-field">
        <label>New Password</label>
        <input type="password" name="password" required>
      </div>
      <div class="form-field">
        <label>Confirm New Password</label>
        <input type="password" name="password_confirmation" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
    </form>
  </div>
</body>
</html>
