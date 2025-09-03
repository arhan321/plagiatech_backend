<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard</title>
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #f4f6f9;
      margin: 0;
      padding: 0;
    }
    header {
      background: #007bff;
      color: #fff;
      padding: 15px 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    header h1 { margin: 0; font-size: 20px; }
    header form button {
      background: #dc3545; border: none; padding: 8px 15px;
      color: #fff; border-radius: 5px; cursor: pointer;
    }
    header form button:hover { background: #c82333; }
    .container { max-width: 1100px; margin: 30px auto; padding: 0 20px; }
    .card {
      background: #fff; border-radius: 10px; padding: 25px;
      margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .card h3 { margin-bottom: 15px; color: #333; }
    input[type="text"], input[type="file"], textarea, button {
      width: 100%; padding: 12px; margin-top: 10px;
      border: 1px solid #ddd; border-radius: 6px; font-size: 14px;
    }
    button {
      background: #28a745; color: #fff; border: none;
      cursor: pointer; transition: 0.3s;
    }
    button:hover { background: #218838; }
    table {
      width: 100%; border-collapse: collapse; margin-top: 15px;
      background: #fff; border-radius: 10px; overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    th {
      background: #007bff; color: #fff; padding: 12px; text-align: left;
    }
    td { padding: 12px; border-bottom: 1px solid #eee; }
    tr:hover td { background: #f9f9f9; }
    a { color: #007bff; text-decoration: none; }
    a:hover { text-decoration: underline; }
    .success { color: #28a745; font-size: 14px; margin-bottom: 10px; }
    .empty { text-align: center; color: #777; padding: 20px; }
    .stats {
      display: flex; gap: 20px; margin-bottom: 20px;
    }
    .stat-box {
      flex: 1; background: #fff; padding: 20px;
      border-radius: 10px; text-align: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .stat-box h2 { margin: 0; font-size: 24px; color: #007bff; }
    .stat-box p { margin: 5px 0 0; color: #555; }
    .actions form { display:inline; }
    .actions button {
      background: #dc3545; padding: 5px 10px; font-size: 12px;
    }
    .actions a {
      padding: 5px 10px; font-size: 12px; border-radius: 4px;
      background: #007bff; color: #fff;
    }
    .actions a:hover { background: #0056b3; }
    .warn { color: #dc3545; font-weight: bold; }
  </style>
</head>
<body>
  <header>
    <h1>Selamat datang, {{ Auth::user()->name }}</h1>
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit">Logout</button>
    </form>
  </header>

  <div class="container">

    <!-- Statistik -->
    <div class="stats">
      <div class="stat-box">
        <h2>{{ $documents->count() }}</h2>
        <p>Total Jurnal</p>
      </div>
      <div class="stat-box">
        <h2>{{ $documents->first()?->created_at->format('d M Y') ?? '-' }}</h2>
        <p>Upload Terakhir</p>
      </div>
    </div>

    <!-- Upload Form -->
    <div class="card">
      <h3>Upload Jurnal Ilmu Komputer</h3>
      @if(session('success'))
        <p class="success">{{ session('success') }}</p>
      @endif
      <form method="POST" action="{{ route('documents.upload') }}" enctype="multipart/form-data">
        @csrf
        <input type="text" name="title" placeholder="Judul Jurnal" required>
        <input type="file" name="file" accept=".pdf,.doc,.docx,.txt" required>
        <button type="submit">Upload</button>
      </form>
    </div>

    <!-- List Documents -->
    <div class="card">
      <h3>Jurnal Anda</h3>
      <table>
        <thead>
          <tr>
            <th>Judul</th>
            <th>File</th>
            <th>Similarity</th>
            <th>Tanggal</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($documents as $doc)
            <tr>
              <td>{{ $doc->title }}</td>
              <td><a href="{{ asset('storage/'.$doc->filename) }}" target="_blank">Lihat</a></td>
              <td>
                @if($doc->similarity >= 70)
                  <span class="warn">{{ $doc->similarity }}%</span>
                @else
                  {{ $doc->similarity ?? '-' }}%
                @endif
              </td>
              <td>{{ $doc->created_at->format('d M Y') }}</td>
              <td class="actions">
                <a href="{{ route('documents.check', $doc->id) }}">Cek</a>
                <form method="POST" action="{{ route('documents.destroy', $doc->id) }}" style="display:inline;">
                  @csrf @method('DELETE')
                  <button type="submit" onclick="return confirm('Yakin hapus?')">Hapus</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="empty">Belum ada jurnal yang diupload.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <!-- Detail Hasil Plagiarisme -->
    @if(session('plagiarism_results'))
      <div class="card">
        <h3>Detail Hasil Cek Plagiarisme</h3>
        <table>
          <thead>
            <tr>
              <th>Judul Sumber</th>
              <th>Similarity</th>
              <th>Lihat</th>
            </tr>
          </thead>
          <tbody>
            @foreach(session('plagiarism_results') as $res)
              <tr>
                <td>{{ $res['title'] }}</td>
                <td>{{ $res['similarity'] }}%</td>
                <td><a href="{{ route('documents.check', $res['doc_id']) }}">Cek</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    <!-- Chat AI -->
    <div class="card">
      <h3>Chat AI Assistant</h3>
      <form method="POST" action="{{ route('chat.ask') }}">
        @csrf
        <textarea name="question" rows="3" placeholder="Tanyakan tentang jurnal atau cara parafrase..."></textarea>
        <button type="submit">Kirim</button>
      </form>

      @if(session('chat_response'))
        <div style="margin-top:15px; padding:10px; background:#f9f9f9; border-radius:6px;">
          <strong>AI:</strong> {{ session('chat_response') }}
        </div>
      @endif
    </div>

  </div>
</body>
</html>
