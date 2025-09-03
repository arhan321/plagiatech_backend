<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f4f6f9; display:flex; justify-content:center; align-items:center; height:100vh; }
    .card { background:#fff; padding:30px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.2); width:350px; }
    input { width:100%; padding:10px; margin:10px 0; border:1px solid #ccc; border-radius:5px; }
    button { width:100%; padding:10px; background:#28a745; color:#fff; border:none; border-radius:5px; cursor:pointer; }
    button:hover { background:#218838; }
    .error { color:red; font-size:14px; }
  </style>
</head>
<body>
  <div class="card">
    <h2 style="text-align:center;">Register</h2>
    <form method="POST" action="/register">
      @csrf
      <input type="text" name="name" placeholder="Nama Lengkap" required>
      <input type="email" name="email" placeholder="Email" required>
      <input type="password" name="password" placeholder="Password" required>
      <input type="password" name="password_confirmation" placeholder="Konfirmasi Password" required>
      @error('email')
        <p class="error">{{ $message }}</p>
      @enderror
      <button type="submit">Register</button>
    </form>
    <p style="text-align:center; margin-top:15px;">
      Sudah punya akun? <a href="/login">Login</a>
    </p>
  </div>
</body>
</html>
