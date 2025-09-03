<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Dashboard</title>
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
  <style>
    :root{
      --bg:#f6f7fb; --card:#ffffff; --text:#1f2937; --muted:#6b7280; --primary:#2563eb; --primary-600:#1d4ed8;
      --accent:#10b981; --warn:#ef4444; --ring:#e5e7eb; --shadow:0 2px 10px rgba(0,0,0,.06);
      --table-head:#f3f4f6; --chip:#111827; --chip-bg:#f3f4f6;
    }
    [data-theme="dark"]{
      --bg:#0b1020; --card:#12172a; --text:#e5edff; --muted:#9aa4c7; --primary:#60a5fa; --primary-600:#3b82f6;
      --accent:#34d399; --warn:#f87171; --ring:#1f2540; --shadow:0 6px 18px rgba(0,0,0,.35);
      --table-head:#121935; --chip:#e5edff; --chip-bg:#1a2140;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{margin:0;background:var(--bg);color:var(--text);font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,'Helvetica Neue',Arial}
    a{color:var(--primary);text-decoration:none}
    a:hover{text-decoration:underline}

    /* Topbar */
    header{position:sticky;top:0;z-index:30;background:linear-gradient(90deg,var(--card),transparent);backdrop-filter:saturate(180%) blur(8px);
      border-bottom:1px solid var(--ring); box-shadow:var(--shadow)}
    .top{max-width:1200px;margin:0 auto;padding:14px 20px;display:flex;gap:16px;align-items:center;justify-content:space-between}
    .brand{display:flex;gap:10px;align-items:center}
    .logo{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,var(--primary),var(--accent));box-shadow:0 4px 14px rgba(37,99,235,.35)}
    .brand h1{margin:0;font-size:16px;letter-spacing:.3px}
    .user{display:flex;gap:10px;align-items:center}
    .btn{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--ring);background:var(--card);color:var(--text);
      padding:8px 12px;border-radius:10px;cursor:pointer;box-shadow:var(--shadow)}
    .btn.primary{background:var(--primary);border-color:transparent;color:#fff}
    .btn:hover{filter:brightness(1.03)}
    .btn.danger{background:var(--warn);color:#fff;border-color:transparent}

    /* Layout */
    .container{max-width:1200px;margin:24px auto;padding:0 20px;display:grid;gap:20px}
    .grid{display:grid;gap:16px}
    .grid.stats{grid-template-columns:repeat(3,minmax(0,1fr))}
    @media (max-width:900px){ .grid.stats{grid-template-columns:repeat(2,minmax(0,1fr))} }
    @media (max-width:600px){ .grid.stats{grid-template-columns:1fr} }

    .card{background:var(--card);border:1px solid var(--ring);border-radius:14px;box-shadow:var(--shadow)}
    .card .body{padding:18px}
    .card .title{font-weight:600;margin:0 0 8px 0}
    .muted{color:var(--muted)}

    /* Stats */
    .stat{display:flex;align-items:center;justify-content:space-between}
    .stat h2{margin:0;font-size:28px}
    .chip{font-size:12px;color:var(--chip);background:var(--chip-bg);padding:4px 8px;border-radius:999px;border:1px solid var(--ring)}

    /* Upload */
    .uploader{border:1px dashed var(--ring);border-radius:12px;padding:14px;background:transparent}
    .uploader input[type=file]{display:block;width:100%;padding:12px;border:1px solid var(--ring);border-radius:10px;background:transparent;color:var(--text)}
    .uploader input[type=text]{display:block;width:100%;padding:12px;border:1px solid var(--ring);border-radius:10px;background:transparent;color:var(--text);margin-bottom:10px}
    .uploader .actions{display:flex;gap:10px;margin-top:10px}

    /* Filters */
    .filters{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
    .input{border:1px solid var(--ring);background:transparent;color:var(--text);padding:10px 12px;border-radius:10px}
    .range{display:flex;gap:10px;align-items:center}

    /* Table */
    table{width:100%;border-collapse:collapse}
    thead th{background:var(--table-head);color:var(--muted);font-weight:600;font-size:12px;letter-spacing:.3px;text-transform:uppercase;border-bottom:1px solid var(--ring);padding:10px}
    tbody td{padding:12px;border-bottom:1px solid var(--ring)}
    .warn{color:var(--warn);font-weight:700}
    .actions a,.actions form button{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--ring);padding:6px 10px;border-radius:8px;background:transparent;color:var(--text)}
    .actions a:hover,.actions form button:hover{background:var(--table-head)}
    .pill{padding:2px 8px;border-radius:999px;font-size:12px}
    .pill.green{background:rgba(16,185,129,.15);color:#10b981}
    .pill.orange{background:rgba(245,158,11,.18);color:#f59e0b}
    .pill.red{background:rgba(239,68,68,.18);color:#ef4444}

    /* Responsive table -> cards */
    @media (max-width:760px){
      table thead{display:none}
      table,tbody,tr,td{display:block;width:100%}
      tbody tr{border:1px solid var(--ring);border-radius:12px;margin-bottom:12px;background:var(--card)}
      tbody td{border-bottom:0;padding:10px 12px}
      tbody td[data-label]:before{content:attr(data-label);display:block;color:var(--muted);font-size:12px;margin-bottom:4px;text-transform:uppercase;letter-spacing:.3px}
      .actions{display:flex;flex-wrap:wrap;gap:8px}
    }

    /* Toast */
    .toast{position:fixed;right:20px;bottom:20px;background:var(--card);border:1px solid var(--ring);padding:12px 14px;border-radius:10px;box-shadow:var(--shadow)}
    .fade-in{animation:fadein .2s ease-out}
    @keyframes fadein{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
  </style>
</head>
<body>
<header>
  <div class="top">
    <div class="brand">
      <div class="logo"></div>
      <h1>Plagiarism Dashboard</h1>
    </div>
    <div class="user">
      <button id="themeToggle" class="btn" type="button" title="Toggle theme">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" stroke="currentColor" stroke-width="1.6"/></svg>
        Tema
      </button>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="btn danger" type="submit">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M9 10v8m6-8v8M4 7h16M7 7l1-3h8l1 3M6 7h12v13a2 2 0 01-2 2H8a2 2 0 01-2-2V7z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
          Logout
        </button>
      </form>
    </div>
  </div>
</header>

<div class="container">

  {{-- ===== Stats + chart ===== --}}
  <div class="grid stats">
    <div class="card">
      <div class="body stat">
        <div>
          <div class="muted">Total Jurnal</div>
          <h2>{{ $documents->count() }}</h2>
        </div>
        <span class="chip">{{ now()->format('d M Y') }}</span>
      </div>
    </div>
    <div class="card">
      <div class="body stat">
        <div>
          <div class="muted">Upload Terakhir</div>
          <h2>{{ $documents->first()?->created_at->format('d M Y') ?? '—' }}</h2>
        </div>
        <span class="chip">Terbaru</span>
      </div>
    </div>
    <div class="card">
      <div class="body stat">
        <div>
          <div class="muted">Rata-rata Similarity</div>
          <h2>
            @php $avg = round((float)($documents->avg('similarity') ?? 0),2); @endphp
            {{ number_format($avg,2) }}%
          </h2>
        </div>
        <span class="chip">{{ $avg >= 70 ? 'Risiko tinggi' : ($avg >= 40 ? 'Sedang' : 'Rendah') }}</span>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="body">
      <p class="title">Tren Similarity</p>
      <canvas id="trend" height="84"></canvas>
    </div>
  </div>

  {{-- ===== Upload ===== --}}
  <div class="card">
    <div class="body">
      <p class="title">Upload Jurnal Ilmu Komputer</p>
      @if(session('success'))
        <div class="toast fade-in" id="toast">{{ session('success') }}</div>
      @endif

      <form method="POST" action="{{ route('documents.upload') }}" enctype="multipart/form-data" class="uploader">
        @csrf
        <input type="text" name="title" placeholder="Judul Jurnal" required>
        <input type="file" name="file" accept=".pdf,.doc,.docx,.txt" required>
        <div class="actions">
          <button class="btn primary" type="submit">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/></svg>
            Upload
          </button>
          <span class="muted" style="font-size:12px">PDF/DOC/DOCX/TXT • maks 10MB</span>
        </div>
      </form>
    </div>
  </div>

  {{-- ===== Documents table with filters ===== --}}
  <div class="card">
    <div class="body">
      <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
        <p class="title" style="margin-bottom:0">Jurnal Anda</p>
        <div class="filters">
          <input id="q" class="input" type="text" placeholder="Cari judul..." />
          <div class="range">
            <label class="muted" style="font-size:12px">Ambang</label>
            <input id="t" type="range" min="0" max="100" value="0" />
            <span id="tVal" class="chip">0%</span>
          </div>
        </div>
      </div>

      <div style="overflow:auto;margin-top:10px">
        <table id="docs">
          <thead>
            <tr>
              <th data-sort="title">Judul</th>
              <th>File</th>
              <th>Receipt</th>
              <th data-sort="similarity">Similarity</th>
              <th data-sort="date">Tanggal</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
          @forelse($documents as $doc)
            @php
              $sim = (float)($doc->similarity ?? 0);
              $badge = $sim >= 70 ? 'red' : ($sim >= 40 ? 'orange' : 'green');
            @endphp
            <tr data-row data-title="{{ Str::lower($doc->title) }}" data-sim="{{ $sim }}" data-date="{{ $doc->created_at->timestamp }}">
              <td data-label="Judul">{{ $doc->title }}</td>
              <td data-label="File"><a href="{{ asset('storage/'.$doc->filename) }}" target="_blank">Lihat</a></td>
              <td data-label="Receipt"><a href="{{ route('documents.receipt', $doc->id) }}">Unduh Receipt (PDF)</a></td>
              <td data-label="Similarity">
                <span class="pill {{ $badge }}">{{ number_format($sim,2) }}%</span>
              </td>
              <td data-label="Tanggal">{{ $doc->created_at->format('d M Y') }}</td>
              <td class="actions" data-label="Aksi" style="white-space:nowrap">
                <a href="{{ route('documents.check',$doc->id) }}" title="Cek ulang">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                  Cek
                </a>
                <form method="POST" action="{{ route('documents.destroy',$doc->id) }}" style="display:inline">
                  @csrf @method('DELETE')
                  <button type="submit" onclick="return confirm('Yakin hapus?')" title="Hapus">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M4 7h16M7 7l1-3h8l1 3M9 10v8m6-8v8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    Hapus
                  </button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="muted">Belum ada jurnal diunggah.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- ===== Detail hasil cek (session) ===== --}}
  @if (session('plagiarism_results'))
    @php $prs = session('plagiarism_results'); @endphp
    @if (is_iterable($prs) && count($prs))
      <div class="card">
        <div class="body">
          <p class="title">Detail Hasil Cek Plagiarisme</p>
          <div style="overflow:auto">
            <table>
              <thead>
                <tr>
                  <th>Judul Sumber</th>
                  <th>Kontribusi</th>
                  <th>Jaccard</th>
                  <th>Cosine</th>
                  <th>Sumber</th>
                  <th>Lihat</th>
                </tr>
              </thead>
              <tbody>
                @foreach($prs as $res)
                  @php
                    $contrib = (float)($res['contrib_pct'] ?? 0);
                    $b = $contrib >= 70 ? 'red' : ($contrib >= 40 ? 'orange' : 'green');
                  @endphp
                  <tr>
                    <td data-label="Judul Sumber">{{ $res['title'] ?? '-' }}</td>
                    <td data-label="Kontribusi"><span class="pill {{ $b }}">{{ number_format($contrib,2) }}%</span></td>
                    <td data-label="Jaccard">{{ number_format((float)($res['jaccard'] ?? 0),2) }}%</td>
                    <td data-label="Cosine">{{ number_format((float)($res['cosine'] ?? 0),2) }}%</td>
                    <td data-label="Sumber">{{ $res['source'] ?? 'arXiv' }}</td>
                    <td data-label="Lihat">
                      @if (!empty($res['pdf_url'])) <a href="{{ $res['pdf_url'] }}" target="_blank">PDF</a> | @endif
                      @if (!empty($res['url'])) <a href="{{ $res['url'] }}" target="_blank">Halaman</a> @else - @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    @endif
  @endif

  {{-- ===== Chat AI ===== --}}
  <div class="card">
    <div class="body">
      <p class="title">Chat AI Assistant</p>
      <form method="POST" action="{{ route('chat.ask') }}">
        @csrf
        <textarea name="question" rows="3" class="input" style="width:100%" placeholder="Tanyakan / parafrase / ringkas..."></textarea>

        {{-- Opsional: kalau sudah implement task/engine/style di controller-mu, munculkan selector --}}
        @if(false)
        <div style="display:flex; gap:10px; margin-top:10px; flex-wrap:wrap">
          <select name="task" class="input">
            <option value="paraphrase">Parafrase</option>
            <option value="chat">Tanya Jawab</option>
            <option value="summarize">Ringkas</option>
          </select>
          <select name="engine" class="input">
            <option value="ollama">Ollama</option>
            <option value="gemini">Gemini</option>
          </select>
          <select name="style" class="input">
            <option value="netral">Netral</option>
            <option value="ilmiah">Ilmiah</option>
            <option value="formal">Formal</option>
            <option value="santai">Santai</option>
          </select>
        </div>
        @endif

        <div style="margin-top:10px">
          <button class="btn primary" type="submit">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 5v14m7-7H5" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/></svg>
            Kirim
          </button>
        </div>
      </form>

      @if(session('chat_response'))
        <div style="margin-top:14px;border:1px solid var(--ring);padding:12px;border-radius:12px;background:var(--card)">
          <strong>AI:</strong> {{ session('chat_response') }}
        </div>
      @endif
    </div>
  </div>

</div>

{{-- ========= JS ========= --}}
@php
  $labels = $documents->pluck('created_at')->map(function($d){ return $d->format('d M'); })->toArray();
  $series = $documents->pluck('similarity')->map(function($v){ return (float)$v; })->toArray();
@endphp
<script>
  // Theme toggle
  (function(){
    const root=document.documentElement;
    const key='theme';
    const saved=localStorage.getItem(key);
    if(saved){ root.setAttribute('data-theme', saved); }
    document.getElementById('themeToggle').addEventListener('click',()=>{
      const cur=root.getAttribute('data-theme') || 'light';
      const next= cur==='light' ? 'dark' : 'light';
      root.setAttribute('data-theme', next);
      localStorage.setItem(key,next);
    });
  })();

  // Toast auto-hide
  const toast=document.getElementById('toast');
  if(toast){ setTimeout(()=> toast.remove(), 3200); }

  // Chart
  (function(){
    const ctx=document.getElementById('trend');
    if(!ctx) return;
    const labels=@json($labels);
    const data=@json($series);
    new Chart(ctx, {
      type:'line',
      data:{
        labels,
        datasets:[{ data, tension:.35, borderColor:getComputedStyle(document.documentElement).getPropertyValue('--primary'), fill:false, pointRadius:2 }]
      },
      options:{
        responsive:true,
        plugins:{ legend:{display:false}, tooltip:{mode:'index', intersect:false} },
        scales:{ x:{ grid:{display:false} }, y:{ beginAtZero:true, suggestedMax:100, ticks:{ callback:v=>v+'%' } } }
      }
    });
  })();

  // Filters & sorting
  (function(){
    const q=document.getElementById('q');
    const t=document.getElementById('t');
    const tVal=document.getElementById('tVal');
    const tbody=document.querySelector('#docs tbody');
    const rows=[...tbody.querySelectorAll('tr[data-row]')];

    function apply(){
      const qq=(q.value||'').toLowerCase();
      const th=parseFloat(t.value||'0');
      rows.forEach(tr=>{
        const title=tr.dataset.title||'';
        const sim=parseFloat(tr.dataset.sim||'0');
        tr.style.display=(title.includes(qq) && sim>=th)?'':'none';
      });
    }
    q && q.addEventListener('input', apply);
    t && t.addEventListener('input', ()=>{ tVal.textContent=t.value+'%'; apply(); });

    // Sorting
    const heads=document.querySelectorAll('#docs thead th[data-sort]');
    let sortKey=null, asc=true;
    heads.forEach(th=>{
      th.style.cursor='pointer';
      th.addEventListener('click', ()=>{
        const key=th.dataset.sort;
        asc = (sortKey===key) ? !asc : true;
        sortKey=key;
        const factor=asc?1:-1;
        const sorted=rows.slice().sort((a,b)=>{
          if(key==='title'){
            return (a.dataset.title > b.dataset.title ? 1 : -1) * factor;
          }
          if(key==='similarity'){
            return (parseFloat(a.dataset.sim)-parseFloat(b.dataset.sim))*factor;
          }
          if(key==='date'){
            return (parseInt(a.dataset.date)-parseInt(b.dataset.date))*factor;
          }
          return 0;
        });
        sorted.forEach(r=>tbody.appendChild(r));
        apply();
      });
    });

    apply();
  })();
</script>
</body>
</html>
