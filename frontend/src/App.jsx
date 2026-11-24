import { useCallback, useEffect, useRef, useState, useSyncExternalStore } from 'react'
import './App.css'

const API_URL = '/EnglishHub/api/index.php'
const BASE_URL = '/EnglishHub'
const CURRENT_YEAR = new Date().getFullYear()

const navigation = [
  { id: 'Dashboard', label: 'Dashboard', icon: 'grid' },
  { id: 'Vocabulary', label: 'Vocabulary', icon: 'book' },
  { id: 'Grammar', label: 'Grammar', icon: 'layers' },
  { id: 'Quiz', label: 'Quiz', icon: 'check' },
  { id: 'Pronunciation', label: 'Pronunciation', icon: 'sound' },
  { id: 'Progress', label: 'My Progress', icon: 'chart' },
]

const iconPaths = {
  grid: <><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /></>,
  book: <><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v17H6.5A2.5 2.5 0 0 1 4 17.5z" /><path d="M4 17.5A2.5 2.5 0 0 1 6.5 15H20M8 7h8M8 10h6" /></>,
  layers: <><path d="m12 3 9 5-9 5-9-5 9-5Z" /><path d="m3 12 9 5 9-5M3 16l9 5 9-5" /></>,
  check: <><circle cx="12" cy="12" r="9" /><path d="m8 12 2.5 2.5L16 9" /></>,
  sound: <><path d="M11 5 6 9H3v6h3l5 4z" /><path d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13" /></>,
  chart: <><path d="M4 19V5M4 19h17" /><path d="m7 15 4-4 3 2 6-7" /></>,
  user: <><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></>,
  settings: <><circle cx="12" cy="12" r="3" /><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.7a7.7 7.7 0 0 1-1.5.9l-.3 1.8h-2.8l-.3-1.8a7.7 7.7 0 0 1-1.5-.9l-1.7.7-1.4-2.4 1.4-1.1a7 7 0 0 1 0-1.8l-1.4-1.1 1.4-2.4 1.7.7a7.7 7.7 0 0 1 1.5-.9l.3-1.8h2.8l.3 1.8a7.7 7.7 0 0 1 1.5.9l1.7-.7 1.4 2.4-1.4 1.1a7 7 0 0 1 0 1.8Z" /></>,
  arrow: <><path d="M5 12h14M13 6l6 6-6 6" /></>,
  search: <><circle cx="10.8" cy="10.8" r="6.8" /><path d="m16 16 5 5" /></>,
  logout: <><path d="M10 17l5-5-5-5M15 12H3" /><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7" /></>,
  bookmark: <><path d="M6 4.5A1.5 1.5 0 0 1 7.5 3h9A1.5 1.5 0 0 1 18 4.5V21l-6-4-6 4z" /></>,
  eye: <><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></>,
  sun: <><circle cx="12" cy="12" r="4" /><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" /></>,
  moon: <path d="M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5 8.6 8.6 0 1 0 20.5 14.2Z" />,
}

function Icon({ name, size = 20 }) {
  return <svg aria-hidden="true" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round">{iconPaths[name]}</svg>
}

function Brand({ onClick, className = '' }) {
  return <a className={`marketing-brand ${className}`} href={`${BASE_URL}/`} onClick={onClick}>
    <span className="brand-mark">E</span><span>English<span className="brand-accent">Hub</span></span>
  </a>
}

async function request(action, { method = 'GET', body, csrfToken, signal, query } = {}) {
  const headers = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (csrfToken) headers['X-CSRF-Token'] = csrfToken
  const searchParams = new URLSearchParams({ action, ...query })
  const response = await fetch(`${API_URL}?${searchParams}`, {
    method,
    headers,
    credentials: 'same-origin',
    body: body === undefined ? undefined : JSON.stringify(body),
    signal,
  })
  const responseText = await response.text()
  let data
  try {
    data = responseText ? JSON.parse(responseText) : null
  } catch {
    throw new Error(`API mengembalikan respons bukan JSON (HTTP ${response.status}). Periksa Apache/PHP dan log server.`)
  }
  if (!data) {
    if (response.status === 502 || response.status === 504) {
      throw new Error('Backend PHP tidak dapat dijangkau. Jalankan Apache XAMPP pada port 80, lalu muat ulang halaman.')
    }
    throw new Error(`API mengembalikan respons kosong (HTTP ${response.status}). Periksa Apache/PHP dan log server.`)
  }
  if (!response.ok) throw new Error(data.error || 'Permintaan gagal. Silakan coba lagi.')
  return data
}

function AuthScreen({ initialMode, onAuthenticated, csrfToken, onTokenChange, onBackHome, backendError, onModeChange }) {
  const mode = initialMode
  const [form, setForm] = useState({ name: '', email: '', password: '', confirm: '' })
  const [error, setError] = useState(backendError || '')
  const [busy, setBusy] = useState(false)
  const [showPassword, setShowPassword] = useState(false)
  const [recoveryNotice, setRecoveryNotice] = useState('')

  function changeMode(nextMode) {
    setError('')
    setRecoveryNotice('')
    onModeChange(nextMode)
  }

  async function submit(event) {
    event.preventDefault()
    setError('')
    if (mode === 'register' && form.password !== form.confirm) {
      setError('Konfirmasi password tidak sama.')
      return
    }
    setBusy(true)
    try {
      const result = await request(mode, {
        method: 'POST',
        csrfToken,
        body: { name: form.name, email: form.email, password: form.password },
      })
      onTokenChange(result.csrfToken)
      onAuthenticated(result.user, mode === 'login' ? `Selamat datang kembali, ${result.user.name}.` : 'Akun berhasil dibuat. Selamat belajar!')
    } catch (submitError) {
      setError(submitError.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="public-auth">
      <header className="auth-topbar">
        <Brand onClick={(event) => { event.preventDefault(); onBackHome() }} />
        <button className="auth-back-link" type="button" onClick={onBackHome}><span aria-hidden="true">←</span> Kembali ke Beranda</button>
      </header>
      <div className="auth-layout">
        <section className="auth-story">
          <div className="auth-story-copy">
            <span className="auth-overline">RUANG BELAJAR BAHASA INGGRIS</span>
            <h1>{mode === 'login' ? <>Masuk ke<br />English<span>Hub</span></> : <>Mulai belajar<br />bersama English<span>Hub</span></>}</h1>
            <p>{mode === 'login' ? 'Lanjutkan progresmu, temukan kata baru, dan bangun kepercayaan diri berbahasa Inggris.' : 'Buat akun siswa untuk mulai belajar kosakata, grammar, dan latihan kuis.'}</p>
          </div>
          <div className="auth-story-links">
            <div>
              <span>{mode === 'login' ? 'Belum punya akun?' : 'Sudah punya akun?'}</span>
              <button type="button" onClick={() => changeMode(mode === 'login' ? 'register' : 'login')}>
                {mode === 'login' ? 'Buat Akun Baru' : 'Masuk'}
              </button>
            </div>
            {mode === 'login' && <button className="auth-forgot" type="button" onClick={() => setRecoveryNotice('Reset kata sandi belum tersedia. Silakan hubungi administrator EnglishHub untuk bantuan.')}>Lupa Kata Sandi?</button>}
          </div>
          {recoveryNotice && <p className="recovery-notice" role="status">{recoveryNotice}</p>}
        </section>
        <section className="auth-panel">
          <div className="auth-form-wrap">
            <span className="auth-form-kicker">ENGLISHHUB LEARNING</span>
            <h2>{mode === 'login' ? 'Selamat datang kembali' : 'Buat akun baru'}</h2>
            <p className="auth-form-description">{mode === 'login' ? 'Masuk dengan email yang terdaftar untuk melanjutkan belajar.' : 'Daftar sebagai siswa dan mulai perjalanan belajarmu.'}</p>
            {error && <div className="auth-error" role="alert">{error}</div>}
            <form onSubmit={submit} className="auth-fields">
              {mode === 'register' && <label>Nama lengkap<input autoComplete="name" required maxLength={100} value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} placeholder="Nama lengkap" /></label>}
              <label>Email<input type="email" autoComplete="email" required maxLength={190} value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} placeholder="nama@email.com" /></label>
              <label>
                Password
                <span className="password-input">
                  <input type={showPassword ? 'text' : 'password'} autoComplete={mode === 'login' ? 'current-password' : 'new-password'} minLength={mode === 'register' ? 8 : undefined} required value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} placeholder={mode === 'register' ? 'Minimal 8 karakter' : 'Masukkan password'} />
                  <button type="button" className="password-toggle" aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'} aria-pressed={showPassword} onClick={() => setShowPassword(!showPassword)}><Icon name="eye" size={18} /></button>
                </span>
              </label>
              {mode === 'register' && <label>Ulangi password<input type="password" autoComplete="new-password" minLength={8} required value={form.confirm} onChange={(event) => setForm({ ...form, confirm: event.target.value })} placeholder="Ketik ulang password" /></label>}
              <button className="auth-submit" disabled={busy}>{busy ? <><span className="auth-spinner" /> Memproses...</> : mode === 'login' ? 'Masuk ke Akun' : 'Buat Akun'}</button>
            </form>
            <p className="auth-security-note">Akun admin dikelola oleh administrator. Pendaftaran umum otomatis sebagai siswa.</p>
          </div>
        </section>
      </div>
    </main>
  )
}

function LandingPage({ onLogin, onRegister, theme, onToggleTheme, backendError }) {
  return (
    <main className={`marketing-page theme-${theme}`}>
      <header className="marketing-nav">
        <div className="marketing-nav-inner">
          <Brand onClick={(event) => { event.preventDefault(); window.scrollTo({ top: 0, behavior: 'smooth' }) }} />
          <nav className="marketing-links" aria-label="Navigasi landing page">
            <a href="#features">Fitur</a>
            <a href="#learning">Jalur Belajar</a>
            <a href="#how-it-works">Cara Kerja</a>
          </nav>
          <div className="marketing-actions">
            <button className="theme-toggle" type="button" onClick={onToggleTheme} aria-label={`Aktifkan mode ${theme === 'dark' ? 'terang' : 'gelap'}`} title={`Mode ${theme === 'dark' ? 'terang' : 'gelap'}`}><Icon name={theme === 'dark' ? 'sun' : 'moon'} size={18} /></button>
            <button className="nav-login" type="button" onClick={onLogin}>Masuk</button>
            <button className="nav-signup" type="button" onClick={onRegister}>Mulai Belajar <Icon name="arrow" size={16} /></button>
          </div>
        </div>
      </header>

      <section className="marketing-hero" id="home">
        <div className="hero-image-overlay" />
        <div className="marketing-container hero-layout">
          <div className="hero-copy">
            <span className="hero-eyebrow"><span className="eyebrow-dot" /> BELAJAR BAHASA INGGRIS, SATU LANGKAH SETIAP HARI</span>
            <h1>English for your<br /><span>next chapter.</span></h1>
            <p>Bangun kemampuan bahasa Inggrismu lewat kosakata, grammar, kuis interaktif, dan latihan pengucapan — dalam satu ruang belajar.</p>
            <div className="hero-buttons">
              <button className="hero-primary" type="button" onClick={onRegister}>Mulai Belajar <Icon name="arrow" size={17} /></button>
              <a className="hero-secondary" href="#features">Pelajari Fitur <span aria-hidden="true">↓</span></a>
            </div>
            <div className="hero-note"><span className="hero-note-check">✓</span> Belajar mandiri dengan progres yang tersimpan di akunmu</div>
          </div>
          <div className="hero-study-card" aria-label="Contoh materi vocabulary">
            <div className="study-card-top"><span className="study-card-mark"><Icon name="book" size={17} /></span><span>VOCABULARY SPOTLIGHT</span><span className="study-card-level">01 / 04</span></div>
            <div className="study-word">confident<span> /ˈkɒn.fɪ.dənt/</span></div>
            <div className="study-meaning">percaya diri</div>
            <div className="study-example">“I feel more confident speaking English.”</div>
            <div className="study-card-bottom"><span>Contoh materi belajar</span><span className="study-progress"><i /></span></div>
          </div>
        </div>
        <a className="hero-scroll" href="#features">SCROLL TO EXPLORE <span aria-hidden="true">↓</span></a>
      </section>

      <section className="marketing-section features-section" id="features">
        <div className="marketing-container">
          <div className="section-intro">
            <span className="section-kicker">LEARN WITH PURPOSE</span>
            <h2>Semua yang kamu butuhkan<br /><span>untuk terus berkembang.</span></h2>
            <p>Materi dan latihan dirancang untuk membantu kamu praktik, bukan hanya menghafal.</p>
          </div>
          <div className="feature-grid">
            <article className="feature-card"><span className="feature-icon"><Icon name="book" size={21} /></span><span className="feature-number">01 / VOCABULARY</span><h3>Words that stay with you</h3><p>Jelajahi bank kosakata, cari arti kata, dengarkan pengucapan, dan tandai progres belajarmu.</p><a href="#learning">Jelajahi vocabulary <Icon name="arrow" size={15} /></a></article>
            <article className="feature-card"><span className="feature-icon"><Icon name="layers" size={21} /></span><span className="feature-number">02 / GRAMMAR</span><h3>Grammar, made clearer</h3><p>Pelajari pola kalimat dengan ringkasan, formula, serta contoh bahasa Inggris dan Indonesia.</p><a href="#learning">Lihat jalur belajar <Icon name="arrow" size={15} /></a></article>
            <article className="feature-card"><span className="feature-icon"><Icon name="check" size={21} /></span><span className="feature-number">03 / QUIZ ARENA</span><h3>Practice together</h3><p>Masuk ke sesi kelas atau quiz room menggunakan kode yang dibagikan guru.</p><a href="#how-it-works">Cara ikut kuis <Icon name="arrow" size={15} /></a></article>
            <article className="feature-card"><span className="feature-icon"><Icon name="sound" size={21} /></span><span className="feature-number">04 / PRONUNCIATION</span><h3>Listen. Repeat. Improve.</h3><p>Dengarkan cara pengucapan kata menggunakan fitur audio yang tersedia di browser.</p><a href="#learning">Lihat fitur latihan <Icon name="arrow" size={15} /></a></article>
          </div>
        </div>
      </section>

      <section className="marketing-section learning-section" id="learning">
        <div className="marketing-container learning-layout">
          <div className="learning-copy">
            <span className="section-kicker">YOUR LEARNING SPACE</span>
            <h2>Belajar dengan ritmemu.<br /><span>Progres tetap terpantau.</span></h2>
            <p>Mulai dari kata baru, pahami grammar, lalu gunakan kemampuanmu lewat kuis. EnglishHub menyimpan aktivitas belajar di akunmu.</p>
            <button className="text-link-button" type="button" onClick={onRegister}>Temukan ruang belajarmu <Icon name="arrow" size={16} /></button>
          </div>
          <div className="learning-stack">
            <article className="learning-row"><span className="learning-icon"><Icon name="book" size={19} /></span><div><strong>Vocabulary learning</strong><small>Pelajari kata, arti, dan progresmu</small></div><span className="learning-arrow">01</span></article>
            <article className="learning-row"><span className="learning-icon"><Icon name="layers" size={19} /></span><div><strong>Grammar essentials</strong><small>Pahami struktur lewat contoh</small></div><span className="learning-arrow">02</span></article>
            <article className="learning-row"><span className="learning-icon"><Icon name="check" size={19} /></span><div><strong>Quiz arena</strong><small>Latihan dengan kode dari guru</small></div><span className="learning-arrow">03</span></article>
            <article className="learning-row"><span className="learning-icon"><Icon name="sound" size={19} /></span><div><strong>Pronunciation practice</strong><small>Dengarkan dan ulangi kata</small></div><span className="learning-arrow">04</span></article>
          </div>
        </div>
      </section>

      <section className="marketing-section process-section" id="how-it-works">
        <div className="marketing-container">
          <div className="section-intro section-intro-centered">
            <span className="section-kicker">HOW ENGLISHHUB WORKS</span>
            <h2>Mulai dalam tiga langkah.</h2>
            <p>Perjalanan belajarmu sederhana dan mudah diikuti.</p>
          </div>
          <div className="steps-grid">
            <article className="step-card"><span>01</span><h3>Buat akun atau masuk</h3><p>Gunakan akun EnglishHub untuk menyimpan progres belajar.</p></article>
            <article className="step-card"><span>02</span><h3>Pilih cara belajar</h3><p>Jelajahi vocabulary, grammar, pronunciation, atau buka Quiz Arena.</p></article>
            <article className="step-card"><span>03</span><h3>Lanjutkan progresmu</h3><p>Tandai kosakata dan pantau hasil latihan yang sudah kamu selesaikan.</p></article>
          </div>
        </div>
      </section>

      <section className="marketing-container final-cta">
        <div><span className="section-kicker">YOUR NEXT CHAPTER STARTS HERE</span><h2>Satu langkah kecil.<br />Satu kemampuan baru.</h2><p>Mulai perjalanan bahasa Inggrismu bersama EnglishHub.</p></div>
        <button className="hero-primary" type="button" onClick={onRegister}>Mulai Belajar <Icon name="arrow" size={17} /></button>
      </section>

      <footer className="marketing-footer">
        <div className="marketing-container footer-inner">
          <Brand onClick={(event) => { event.preventDefault(); window.scrollTo({ top: 0, behavior: 'smooth' }) }} />
          <p>Ruang belajar bahasa Inggris untuk terus berkembang.</p>
          <div><a href="#features">Fitur</a><a href="#how-it-works">Cara Kerja</a><button type="button" onClick={onLogin}>Masuk</button></div>
          <small>© {CURRENT_YEAR} EnglishHub. Belajar hari ini, berkembang setiap hari.</small>
        </div>
      </footer>
      {backendError && <div className="backend-status" role="status">Layanan sedang sulit dijangkau. Coba muat ulang atau masuk kembali beberapa saat lagi.</div>}
    </main>
  )
}

function Metric({ label, value, note, icon }) {
  return <article className="metric-card"><div className="metric-head"><span>{label}</span><span className="metric-icon"><Icon name={icon} /></span></div><strong>{value}</strong><small>{note}</small></article>
}

function EmptyState({ children }) {
  return <div className="empty-state">{children}</div>
}

function getPublicRoute() {
  if (window.location.hash === '#/register') return 'register'
  if (window.location.hash === '#/login') return 'login'
  return 'home'
}

function subscribePublicRoute(onChange) {
  window.addEventListener('hashchange', onChange)
  window.addEventListener('popstate', onChange)
  return () => {
    window.removeEventListener('hashchange', onChange)
    window.removeEventListener('popstate', onChange)
  }
}

function App() {
  const [user, setUser] = useState(null)
  const [csrfToken, setCsrfToken] = useState('')
  const publicRoute = useSyncExternalStore(subscribePublicRoute, getPublicRoute, getPublicRoute)
  const [theme, setTheme] = useState(() => window.localStorage.getItem('englishhub-theme') === 'light' ? 'light' : 'dark')
  const [activePage, setActivePage] = useState('Dashboard')
  const [loading, setLoading] = useState(true)
  const [pageData, setPageData] = useState(null)
  const [pageLoading, setPageLoading] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [search, setSearch] = useState('')
  const [profileForm, setProfileForm] = useState({ name: '', email: '' })
  const [profileMenuOpen, setProfileMenuOpen] = useState(false)
  const profileMenuRef = useRef(null)
  const [pendingQuizRoom, setPendingQuizRoom] = useState(null)

  useEffect(() => {
    window.localStorage.setItem('englishhub-theme', theme)
  }, [theme])

  useEffect(() => {
    if (!window.location.hash) window.scrollTo(0, 0)
    request('me')
      .then(({ user: currentUser, csrfToken: token }) => {
        setUser(currentUser)
        setCsrfToken(token)
        if (currentUser) setProfileForm({ name: currentUser.name, email: currentUser.email })
      })
      .catch((loadError) => setError(loadError.message))
      .finally(() => setLoading(false))
  }, [])

  useEffect(() => {
    if (!profileMenuOpen) return undefined

    function closeProfileMenu(event) {
      if (!profileMenuRef.current?.contains(event.target)) setProfileMenuOpen(false)
    }

    function closeProfileMenuOnEscape(event) {
      if (event.key === 'Escape') {
        setProfileMenuOpen(false)
        profileMenuRef.current?.querySelector('button')?.focus()
      }
    }

    document.addEventListener('pointerdown', closeProfileMenu)
    document.addEventListener('keydown', closeProfileMenuOnEscape)
    return () => {
      document.removeEventListener('pointerdown', closeProfileMenu)
      document.removeEventListener('keydown', closeProfileMenuOnEscape)
    }
  }, [profileMenuOpen])

  function navigatePublic(route) {
    const url = `${window.location.pathname}${window.location.search}${route === 'home' ? '' : `#/${route}`}`
    window.history.pushState(null, '', url)
    window.dispatchEvent(new Event('popstate'))
    if (route === 'home') window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  function handleAuthenticated(authenticatedUser, message) {
    navigatePublic('home')
    setUser(authenticatedUser)
    setProfileForm({ name: authenticatedUser.name, email: authenticatedUser.email })
    setError('')
    setNotice(message)
  }

  const loadPage = useCallback(async (page, query, signal) => {
    if (!user) return
    const action = {
      Dashboard: 'dashboard',
      Vocabulary: 'vocabulary',
      Grammar: 'grammar',
      Progress: 'progress',
      Pronunciation: 'vocabulary',
    }[page]
    if (!action) {
      setPageData(null)
      return
    }
    setPageLoading(true)
    setError('')
    try {
      const data = await request(action, { signal, query: page === 'Vocabulary' && query ? { q: query } : undefined })
      setPageData(data)
    } catch (loadError) {
      if (loadError.name !== 'AbortError') setError(loadError.message)
    } finally {
      if (!signal?.aborted) setPageLoading(false)
    }
  }, [user])

  useEffect(() => {
    if (!user) return undefined
    const controller = new AbortController()
    const delay = activePage === 'Vocabulary' ? window.setTimeout(() => loadPage(activePage, search, controller.signal), 250) : (loadPage(activePage, '', controller.signal), null)
    return () => {
      controller.abort()
      if (delay) window.clearTimeout(delay)
    }
  }, [activePage, search, user, loadPage])

  function navigate(page) {
    setActivePage(page)
    if (page !== 'Quiz') setPendingQuizRoom(null)
    setError('')
    setNotice('')
  }

  async function logout() {
    setProfileMenuOpen(false)
    if (!window.confirm('Apakah kamu yakin ingin keluar?')) return

    try {
      await request('logout', { method: 'POST', csrfToken, body: {} })
      setUser(null)
      setPageData(null)
      setCsrfToken('')
      setError('')
      setNotice('')
      navigatePublic('login')
    } catch (logoutError) {
      setError(logoutError.message)
    }
  }

  async function joinActivityRoom(code) {
    const result = await request('room_join', { method: 'POST', csrfToken, body: { code } })
    if (result.mode === 'assignment') {
      window.location.href = `${BASE_URL}/${result.redirect}`
      return
    }
    if (result.mode === 'legacy-room') {
      window.location.href = `${BASE_URL}/${result.redirect}`
      return
    }
    setPendingQuizRoom({ session_id: result.session_id, is_host: false, code: result.session_code })
    navigate('Quiz')
  }

  async function updateVocabulary(item, action, value) {
    try {
      await request(action, {
        method: 'POST',
        csrfToken,
        body: { vocabularyId: Number(item.id), ...(action === 'bookmark' ? { bookmarked: value } : {}) },
      })
      setPageData((previous) => ({
        ...previous,
        items: previous.items.map((word) => word.id === item.id
          ? { ...word, [action === 'bookmark' ? 'bookmarked' : 'studied']: action === 'bookmark' ? Number(value) : 1 }
          : word),
      }))
      setNotice(action === 'bookmark' ? (value ? 'Kosakata disimpan ke bookmark.' : 'Bookmark dihapus.') : 'Kosakata ditandai sudah dipelajari.')
    } catch (actionError) {
      setError(actionError.message)
    }
  }

  async function saveProfile(event) {
    event.preventDefault()
    setError('')
    try {
      const result = await request('profile', { method: 'POST', csrfToken, body: profileForm })
      setUser(result.user)
      setNotice('Profil berhasil diperbarui.')
    } catch (saveError) {
      setError(saveError.message)
    }
  }

  if (loading) return <div className="boot-screen"><span className="brand-mark">E</span><p>Menyiapkan EnglishHub...</p></div>
  if (!user && (publicRoute === 'login' || publicRoute === 'register')) {
    return <AuthScreen
      initialMode={publicRoute}
      csrfToken={csrfToken}
      onTokenChange={setCsrfToken}
      onAuthenticated={handleAuthenticated}
      onBackHome={() => navigatePublic('home')}
      backendError={error}
      onModeChange={(mode) => navigatePublic(mode)}
    />
  }
  if (!user) {
    return <LandingPage
      onLogin={() => navigatePublic('login')}
      onRegister={() => navigatePublic('register')}
      theme={theme}
      onToggleTheme={() => setTheme((currentTheme) => currentTheme === 'dark' ? 'light' : 'dark')}
      backendError={Boolean(error)}
    />
  }

  const pageTitles = {
    Dashboard: ['Ringkasan belajar', 'Semua aktivitas belajarmu dalam satu tempat.'],
    Vocabulary: ['Bank kosakata', 'Cari, dengarkan, dan tandai kosakata yang sudah kamu pelajari.'],
    Grammar: ['Grammar essentials', 'Pahami pola tata bahasa lewat contoh yang jelas.'],
    Quiz: ['Latihan & kuis', 'Pilih kuis yang tersedia dari kelas atau quiz room.'],
    Pronunciation: ['Latihan pengucapan', 'Dengarkan pengucapan kata menggunakan suara browser.'],
    Progress: ['Progres belajar', 'Lihat hasil kuis dan perkembangan kosakatamu.'],
    Profile: ['Pengaturan profil', 'Perbarui informasi akun EnglishHub-mu.'],
    Settings: ['Pengaturan akun', 'Kelola informasi akun EnglishHub-mu.'],
  }
  const [title, subtitle] = pageTitles[activePage]

  return (
    <div className={`app-shell theme-${theme}`}>
      <aside className="sidebar">
        <a className="brand" href="#" onClick={(event) => { event.preventDefault(); navigate('Dashboard') }}><span className="brand-mark">E</span> EnglishHub</a>
        <div className="sidebar-label">WORKSPACE</div>
        <nav className="side-nav" aria-label="Navigasi utama">
          {navigation.map((item) => <button key={item.id} className={`nav-link ${activePage === item.id ? 'active' : ''}`} onClick={() => navigate(item.id)}><Icon name={item.icon} /><span>{item.label}</span></button>)}
        </nav>
        {user.role === 'admin' && <div className="admin-links"><div className="sidebar-label">ADMINISTRATION</div><a href={`${BASE_URL}/admin/quizzes.php`}>Kelola kuis</a><a href={`${BASE_URL}/admin/quiz_rooms.php`}>Quiz rooms</a><a href={`${BASE_URL}/admin/users.php`}>Kelola pengguna</a><a href={`${BASE_URL}/admin/vocabularies.php`}>Bank kosakata</a></div>}
        <div className="sidebar-footer"><div className="mini-avatar">{user.name.slice(0, 1).toUpperCase()}</div><div className="sidebar-user"><strong>{user.name}</strong><small>{user.role === 'admin' ? 'Administrator' : 'Student account'}</small></div><button className="icon-button" title="Keluar" onClick={logout}><Icon name="logout" /></button></div>
      </aside>

      <main className="main-area">
        <header className="topbar">
          <div><div className="breadcrumb">Learning space <span>/</span> {activePage}</div><h1>{title}</h1></div>
          <div className="topbar-actions">
            <button
              className="app-theme-toggle"
              type="button"
              aria-label={`Aktifkan mode ${theme === 'dark' ? 'terang' : 'gelap'}`}
              aria-pressed={theme === 'dark'}
              title={`Mode ${theme === 'dark' ? 'terang' : 'gelap'}`}
              onClick={() => setTheme((currentTheme) => currentTheme === 'dark' ? 'light' : 'dark')}
            >
              <Icon name={theme === 'dark' ? 'sun' : 'moon'} size={18} />
            </button>
            <div className="profile-menu" ref={profileMenuRef}>
              <button
                className="profile-chip"
                type="button"
                aria-haspopup="menu"
                aria-expanded={profileMenuOpen}
                aria-label={`Menu akun ${user.name}`}
                onClick={() => setProfileMenuOpen((isOpen) => !isOpen)}
              >
                <span className="mini-avatar">{user.name.slice(0, 1).toUpperCase()}</span>
                <span><strong>{user.name}</strong><small>{user.role === 'admin' ? 'Administrator' : 'Student'}</small></span>
                <span className="chevron">⌄</span>
              </button>
              {profileMenuOpen && <div className="profile-dropdown" role="menu" aria-label="Menu akun">
                <div className="profile-dropdown-user">
                  <span className="mini-avatar">{user.name.slice(0, 1).toUpperCase()}</span>
                  <span><strong>{user.name}</strong><small>{user.email}</small></span>
                </div>
                <div className="profile-dropdown-divider" />
                <button type="button" role="menuitem" onClick={() => { setProfileMenuOpen(false); navigate('Profile') }}><Icon name="user" size={17} /> Profil Saya</button>
                <button type="button" role="menuitem" onClick={() => { setProfileMenuOpen(false); navigate('Settings') }}><Icon name="settings" size={17} /> Pengaturan Akun</button>
                <div className="profile-dropdown-divider" />
                <button className="profile-logout" type="button" role="menuitem" onClick={logout}><Icon name="logout" size={17} /> Keluar</button>
              </div>}
            </div>
          </div>
        </header>
        <div className="content">
          <div className="page-lead"><p>{subtitle}</p></div>
          {error && <div className="notice notice-error" role="alert">{error}<button onClick={() => setError('')} aria-label="Tutup">×</button></div>}
          {notice && <div className="notice notice-success" role="status">{notice}<button onClick={() => setNotice('')} aria-label="Tutup">×</button></div>}
          {pageLoading && <div className="loading-bar" aria-label="Memuat data" />}

          {activePage === 'Dashboard' && <DashboardPage data={pageData} user={user} onNavigate={navigate} onJoinRoom={joinActivityRoom} />}
          {activePage === 'Vocabulary' && <VocabularyPage data={pageData} search={search} onSearch={setSearch} onAction={updateVocabulary} />}
          {activePage === 'Grammar' && <GrammarPage data={pageData} />}
          {activePage === 'Quiz' && <QuizPage csrfToken={csrfToken} initialRoom={pendingQuizRoom} />}
          {activePage === 'Pronunciation' && <PronunciationPage data={pageData} />}
          {activePage === 'Progress' && <ProgressPage data={pageData} />}
          {activePage === 'Profile' && <ProfilePage form={profileForm} onChange={setProfileForm} onSubmit={saveProfile} />}
          {activePage === 'Settings' && <ProfilePage form={profileForm} onChange={setProfileForm} onSubmit={saveProfile} />}
        </div>
        <footer className="app-footer">EnglishHub <span>·</span> Belajar hari ini, berkembang setiap hari.</footer>
      </main>
    </div>
  )
}

function DashboardPage({ data, user, onNavigate, onJoinRoom }) {
  const [joinCode, setJoinCode] = useState('')
  const [joinError, setJoinError] = useState('')
  if (!data) return <EmptyState>Data dashboard belum tersedia.</EmptyState>
  const joinForm = (
    <form className="join-code-form" onSubmit={async (event) => {
        event.preventDefault()
        setJoinError('')
        try {
          await onJoinRoom(joinCode)
        } catch (joinRequestError) {
          setJoinError(joinRequestError.message)
        }
      }}>
      <label htmlFor="room-code">Kode room</label>
      <div>
        <input id="room-code" name="code" value={joinCode} onChange={(event) => setJoinCode(event.target.value.toUpperCase())} maxLength={12} required placeholder="Masukkan kode room" autoComplete="off" />
        <button type="submit">Gabung <Icon name="arrow" size={17} /></button>
      </div>
      {joinError && <p className="join-error" role="alert">{joinError}</p>}
    </form>
  )
  if (data.role === 'admin') {
    return <>
      <section className="welcome-panel"><div><span className="eyebrow eyebrow-light">ADMIN WORKSPACE</span><h2>Selamat datang, {user.name}.</h2><p>Pantau platform dan kelola aktivitas pembelajaran siswa.</p></div><div className="welcome-art"><Icon name="chart" size={72} /></div></section>
      <section className="metrics-grid">
        <Metric label="Siswa terdaftar" value={data.counts.students} note="Akun siswa" icon="user" />
        <Metric label="Bank kosakata" value={data.counts.vocabularies} note="Kosakata aktif" icon="book" />
        <Metric label="Kuis" value={data.counts.quizzes} note="Materi kuis" icon="check" />
        <Metric label="Quiz rooms" value={data.counts.quiz_rooms} note="Ruang latihan" icon="grid" />
      </section>
      <section className="section-block"><SectionHeading title="Aktivitas yang ditugaskan" subtitle="Kelola kuis, siswa, dan ruang latihan dari satu tempat." /><div className="activity-grid"><LegacyAction title="Buat kuis" text="Tambahkan pertanyaan dan pilihan jawaban." href={`${BASE_URL}/admin/create_quiz.php`} /><LegacyAction title="Buat quiz room" text="Atur waktu dan bagikan kode ruang kuis." href={`${BASE_URL}/admin/create_quiz_room.php`} /><LegacyAction title="Lihat hasil" text="Pantau skor dan progres pengerjaan siswa." href={`${BASE_URL}/admin/results.php`} /></div></section>
      <section className="section-block"><SectionHeading title="Kelola EnglishHub" subtitle="Buka area administrasi yang ingin dikelola." /><div className="action-grid"><LegacyAction title="Manajemen kuis" text="Kelola pertanyaan dan sesi kelas." href={`${BASE_URL}/admin/quizzes.php`} /><LegacyAction title="Quiz rooms" text="Atur latihan berbasis kosakata." href={`${BASE_URL}/admin/quiz_rooms.php`} /><LegacyAction title="Vocabulary bank" text="Kelola daftar kosakata aktif." href={`${BASE_URL}/admin/vocabularies.php`} /></div></section>
    </>
  }
  const stats = data.stats || {}
  const progress = data.vocabularyCount ? Math.min(100, Math.round((Number(data.wordsLearned) / Number(data.vocabularyCount)) * 100)) : 0
  const completedQuizzes = Number(stats.total_quiz || 0)
  const latestQuiz = data.recentResults?.[0]
  const quickLinks = [
    { title: 'Vocabulary', description: 'Pelajari kata baru', icon: 'book', page: 'Vocabulary' },
    { title: 'Grammar', description: 'Pahami pola kalimat', icon: 'layers', page: 'Grammar' },
    { title: 'Quiz', description: 'Latihan dan kuis', icon: 'check', page: 'Quiz' },
    { title: 'Pronunciation', description: 'Latih pengucapan', icon: 'sound', page: 'Pronunciation' },
    { title: 'My Progress', description: 'Lihat perkembangan', icon: 'chart', page: 'Progress' },
  ]
  return <>
    <div className="student-dashboard">
      <section className="join-hero dashboard-join-hero">
        <div className="join-hero-copy">
          <span className="join-kicker">QUIZ ARENA</span>
          <h2>Bergabung ke Aktivitas Belajar</h2>
          <p>Masukkan kode dari guru atau host untuk masuk ke quiz room.</p>
        </div>
        <div className="dashboard-join-form-wrap">
          {joinForm}
          <small>Gunakan kode yang dibagikan untuk Live Quiz atau Assignment.</small>
        </div>
      </section>

      <section className="dashboard-learning-hero">
        <div className="dashboard-learning-copy">
          <span className="eyebrow eyebrow-light">RUANG BELAJAR</span>
          <h2>Terus berkembang, {user.name.split(' ')[0]}.</h2>
          <p>{data.latestVocabulary ? `Terakhir dipelajari: ${data.latestVocabulary.word_en}.` : 'Bangun kebiasaan belajar lewat kosakata, grammar, dan latihan.'}</p>
          <button className="button button-white" onClick={() => onNavigate('Vocabulary')}>Lanjutkan belajar <Icon name="arrow" size={16} /></button>
        </div>
        <div className="dashboard-vocabulary-progress">
          <div className="dashboard-progress-heading"><span>PROGRES KOSAKATA</span><strong>{data.wordsLearned} <small>/ {data.vocabularyCount}</small></strong></div>
          <div className="dashboard-progress-track" role="progressbar" aria-label="Progres kosakata" aria-valuemin={0} aria-valuemax={100} aria-valuenow={progress}>
            <span style={{ width: `${progress}%` }} />
          </div>
          <small>{progress}% dari kosakata aktif dipelajari</small>
        </div>
        <div className="dashboard-learning-art" aria-hidden="true"><Icon name="book" size={27} /></div>
      </section>

      <section className="dashboard-stats-grid" aria-label="Statistik pembelajaran">
        <article className="dashboard-stat-card"><span className="dashboard-stat-icon"><Icon name="book" size={18} /></span><div><small>Kosakata dipelajari</small><strong>{data.wordsLearned} <span>/ {data.vocabularyCount}</span></strong></div></article>
        <article className="dashboard-stat-card"><span className="dashboard-stat-icon"><Icon name="check" size={18} /></span><div><small>Kuis diselesaikan</small><strong>{completedQuizzes}</strong></div></article>
        <article className="dashboard-stat-card"><span className="dashboard-stat-icon"><Icon name="chart" size={18} /></span><div><small>Rata-rata skor</small><strong>{completedQuizzes ? `${Math.round(Number(stats.avg_score || 0))}%` : '—'}</strong></div></article>
        <article className="dashboard-stat-card"><span className="dashboard-stat-icon"><Icon name="grid" size={18} /></span><div><small>Waktu belajar</small><strong>{formatStudyDuration(Number(stats.study_seconds || 0))}</strong><small className="dashboard-stat-note">Durasi kuis selesai</small></div></article>
      </section>

      <section className="dashboard-section">
        <SectionHeading title="Akses cepat" subtitle="Pilih aktivitas belajar untuk melanjutkan." />
        <div className="dashboard-quick-grid">
          {quickLinks.map((item) => <button className="dashboard-quick-card" key={item.page} onClick={() => onNavigate(item.page)}>
            <span className="dashboard-quick-icon"><Icon name={item.icon} size={19} /></span>
            <span className="dashboard-quick-copy"><strong>{item.title}</strong><small>{item.description}</small></span>
            <Icon name="arrow" size={16} />
          </button>)}
        </div>
      </section>

      <section className="dashboard-section">
        <SectionHeading title="Aktivitas belajar terbaru" subtitle="Materi dan hasil yang benar-benar tercatat di akunmu." />
        <div className="dashboard-activity-layout">
          <div className="dashboard-highlights">
            <article className="dashboard-highlight-card">
              <span className="dashboard-highlight-icon"><Icon name="book" size={17} /></span>
              <div><small>KOSAKATA TERAKHIR</small><strong>{data.latestVocabulary?.word_en || 'Belum ada materi dipelajari'}</strong><p>{data.latestVocabulary?.meaning_id || 'Aktivitas kosakata yang dipelajari akan muncul di sini.'}</p></div>
              <button onClick={() => onNavigate('Vocabulary')} aria-label="Buka Vocabulary"><Icon name="arrow" size={16} /></button>
            </article>
            <article className="dashboard-highlight-card">
              <span className="dashboard-highlight-icon"><Icon name="check" size={17} /></span>
              <div><small>KUIS TERAKHIR</small><strong>{latestQuiz?.title || 'Belum ada kuis dikerjakan'}</strong><p>{latestQuiz ? `Skor ${latestQuiz.score} dari ${latestQuiz.max_score}` : 'Hasil kuis yang sudah dikumpulkan akan muncul di sini.'}</p></div>
              <button onClick={() => onNavigate('Quiz')} aria-label="Buka Quiz"><Icon name="arrow" size={16} /></button>
            </article>
          </div>
          <div className="dashboard-activity-panel">
            <div className="dashboard-activity-heading"><h3>Riwayat aktivitas</h3><button onClick={() => onNavigate('Progress')}>Lihat progres <Icon name="arrow" size={14} /></button></div>
            {data.recentActivities?.length ? <div className="dashboard-activity-list">{data.recentActivities.map((activity, index) => <article className="dashboard-activity-row" key={`${activity.type}-${activity.occurred_at}-${index}`}>
              <span className="dashboard-activity-icon"><Icon name={activity.type === 'quiz' ? 'check' : 'book'} size={15} /></span>
              <div><strong>{activity.title}</strong><small>{activity.detail}</small></div>
              <time dateTime={activity.occurred_at}>{formatActivityDate(activity.occurred_at)}</time>
            </article>)}</div> : <EmptyState>Belum ada aktivitas belajar.</EmptyState>}
          </div>
        </div>
      </section>
    </div>
  </>
}

function formatStudyDuration(seconds) {
  if (seconds <= 0) return '0 mnt'
  const minutes = Math.floor(seconds / 60)
  if (minutes === 0) return '<1 mnt'
  const hours = Math.floor(minutes / 60)
  return hours ? `${hours}j ${minutes % 60}m` : `${minutes} mnt`
}

function formatActivityDate(value) {
  return new Date(value.replace(' ', 'T')).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
}

function SectionHeading({ title, subtitle }) {
  return <div className="section-heading"><h2>{title}</h2><p>{subtitle}</p></div>
}

function LegacyAction({ title, text, href }) {
  return <a className="action-card action-link" href={href}><span className="action-icon"><Icon name="arrow" /></span><strong>{title}</strong><small>{text}</small><span className="action-arrow">Kelola <Icon name="arrow" size={16} /></span></a>
}

function VocabularyPage({ data, search, onSearch, onAction }) {
  const items = data?.items || []
  return <section className="section-block">
    <div className="toolbar"><label className="search-field"><Icon name="search" /><input value={search} onChange={(event) => onSearch(event.target.value)} placeholder="Cari kata atau terjemahan..." /></label><span className="result-count">{items.length} kosakata</span></div>
    {items.length ? <div className="vocabulary-grid">{items.map((item) => <article className="vocabulary-card" key={item.id}><div className="word-number">WORD {String(item.vocab_number).padStart(3, '0')}</div><h2>{item.word_en}</h2><p>{item.meaning_id}</p><div className="word-actions"><button title={`Dengarkan ${item.word_en}`} onClick={() => speak(item.word_en)}><Icon name="sound" size={17} /> Dengarkan</button><button className={Number(item.bookmarked) ? 'bookmarked' : ''} title={Number(item.bookmarked) ? 'Hapus bookmark' : 'Simpan bookmark'} onClick={() => onAction(item, 'bookmark', !Number(item.bookmarked))}><Icon name="bookmark" size={17} /> {Number(item.bookmarked) ? 'Tersimpan' : 'Simpan'}</button><button className="study-button" disabled={Number(item.studied)} onClick={() => onAction(item, 'study')}><span>{Number(item.studied) ? 'Sudah dipelajari' : 'Tandai dipelajari'}</span></button></div></article>)}</div> : <EmptyState>{search ? 'Tidak ditemukan. Coba kata pencarian lain.' : 'Bank kosakata belum berisi kata aktif.'}</EmptyState>}
  </section>
}

function GrammarPage({ data }) {
  const items = data?.items || []
  return items.length ? <section className="grammar-grid">{items.map((lesson) => <article className="grammar-card" key={lesson.id}><span className="lesson-kicker">GRAMMAR NOTE {String(lesson.sort_order).padStart(2, '0')}</span><h2>{lesson.title}</h2><p>{lesson.summary}</p><div className="formula-label">POLA KALIMAT</div><div className="formula">{lesson.formula}</div><div className="example"><strong>{lesson.example_en}</strong><span>{lesson.example_id}</span></div></article>)}</section> : <EmptyState>Materi grammar belum tersedia. Pastikan migrasi pembelajaran sudah diterapkan.</EmptyState>
}

function createQuestion() {
  return {
    question_text: '',
    explanation: '',
    image_path: '',
    duration_seconds: 30,
    options: ['', '', '', ''].map((option_text, index) => ({ option_text, is_correct: index === 0 })),
  }
}

function QuizPage({ csrfToken, initialRoom }) {
  const [quizzes, setQuizzes] = useState([])
  const [categories, setCategories] = useState([])
  const [vocabularyCategories, setVocabularyCategories] = useState([])
  const [sessions, setSessions] = useState([])
  const [editor, setEditor] = useState(null)
  const [activeRoom, setActiveRoom] = useState(initialRoom)
  const [roomState, setRoomState] = useState(null)
  const [roomCode, setRoomCode] = useState('')
  const [sessionDraft, setSessionDraft] = useState(null)
  const [assignmentResult, setAssignmentResult] = useState(null)
  const [vocabCategoryId, setVocabCategoryId] = useState('')
  const [vocabQuestionCount, setVocabQuestionCount] = useState(10)
  const [answerOption, setAnswerOption] = useState(null)
  const [previewOpen, setPreviewOpen] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  async function loadQuizzes() {
    const [quizResult, categoryResult, sessionResult] = await Promise.all([
      request('quiz_list'),
      request('quiz_categories'),
      request('quiz_sessions'),
    ])
    setQuizzes(quizResult.quizzes)
    setCategories(categoryResult.categories)
    setVocabularyCategories(categoryResult.vocabularyCategories)
    setSessions(sessionResult.sessions)
  }

  useEffect(() => {
    let active = true
    Promise.all([request('quiz_list'), request('quiz_categories'), request('quiz_sessions')])
      .then(([quizResult, categoryResult, sessionResult]) => {
        if (!active) return
        setQuizzes(quizResult.quizzes)
        setCategories(categoryResult.categories)
        setVocabularyCategories(categoryResult.vocabularyCategories)
        setSessions(sessionResult.sessions)
      })
      .catch((loadError) => { if (active) setError(loadError.message) })
    return () => { active = false }
  }, [])

  useEffect(() => {
    if (!activeRoom) return undefined
    let active = true
    let timer
    async function poll() {
      try {
        const state = await request('room_state', { query: { session_id: activeRoom.session_id } })
        if (active) setRoomState(state)
      } catch (pollError) {
        if (active) setError(pollError.message)
      }
      if (active) timer = window.setTimeout(poll, 1200)
    }
    poll()
    return () => {
      active = false
      window.clearTimeout(timer)
    }
  }, [activeRoom])

  function startNewQuiz() {
    setError('')
    setNotice('')
    setEditor({
      id: null,
      title: '',
      description: '',
      category_id: '',
      difficulty: 'beginner',
      visibility: 'private',
      method: 'manual',
      questions: [createQuestion()],
    })
  }

  async function editQuiz(quizId) {
    setBusy(true)
    setError('')
    try {
      const { quiz, questions } = await request('quiz_detail', { query: { id: quizId } })
      setEditor({
        ...quiz,
        category_id: quiz.category_id || '',
        method: 'manual',
        questions: questions.map((question) => ({
          ...question,
          options: [...question.options, ...Array(Math.max(0, 4 - question.options.length)).fill(null)]
            .slice(0, 4)
            .map((option) => option || { option_text: '', is_correct: 0 }),
        })),
      })
    } catch (loadError) {
      setError(loadError.message)
    } finally {
      setBusy(false)
    }
  }

  function updateQuestion(index, field, value) {
    setEditor((current) => ({
      ...current,
      questions: current.questions.map((question, questionIndex) => questionIndex === index
        ? { ...question, [field]: value }
        : question),
    }))
  }

  function updateOption(questionIndex, optionIndex, field, value) {
    setEditor((current) => ({
      ...current,
      questions: current.questions.map((question, index) => index === questionIndex
        ? { ...question, options: question.options.map((option, optionPosition) => optionPosition === optionIndex
          ? { ...option, [field]: value }
          : option) }
        : question),
    }))
  }

  async function saveDraft() {
    setBusy(true)
    setError('')
    try {
      const result = await request('quiz_save', {
        method: 'POST',
        csrfToken,
        body: { ...editor, category_id: editor.category_id || null },
      })
      setEditor((current) => ({ ...current, id: result.id, status: 'draft' }))
      setNotice(result.message)
      await loadQuizzes()
      return result.id
    } catch (saveError) {
      setError(saveError.message)
      return null
    } finally {
      setBusy(false)
    }
  }

  async function publishQuiz() {
    const quizId = await saveDraft()
    if (!quizId) return
    setBusy(true)
    try {
      const result = await request('quiz_publish', { method: 'POST', csrfToken, body: { id: quizId } })
      setNotice(result.message)
      await loadQuizzes()
      setEditor(null)
    } catch (publishError) {
      setError(publishError.message)
    } finally {
      setBusy(false)
    }
  }

  async function deleteQuiz(quizId) {
    if (!window.confirm('Hapus kuis ini? Kuis yang sudah memiliki sesi akan diarsipkan agar riwayat peserta tetap aman.')) return
    setBusy(true)
    setError('')
    try {
      const result = await request('quiz_delete', { method: 'POST', csrfToken, body: { id: quizId } })
      setNotice(result.message)
      await loadQuizzes()
    } catch (deleteError) {
      setError(deleteError.message)
    } finally {
      setBusy(false)
    }
  }

  async function duplicateQuiz(quizId) {
    setBusy(true)
    setError('')
    try {
      const result = await request('quiz_duplicate', { method: 'POST', csrfToken, body: { id: quizId } })
      await loadQuizzes()
      await editQuiz(result.id)
      setNotice(result.message)
    } catch (duplicateError) {
      setError(duplicateError.message)
    } finally {
      setBusy(false)
    }
  }

  async function generateVocabularyQuestions() {
    setBusy(true)
    setError('')
    try {
      const query = { limit: 100 }
      if (vocabCategoryId) query.category_id = vocabCategoryId
      const { items } = await request('quiz_vocabularies', { query })
      const count = Math.max(1, Math.min(100, Number(vocabQuestionCount) || 10))
      const generated = items.slice(0, count).map((vocabulary) => {
        const distractors = items
          .filter((item) => item.vocab_number !== vocabulary.vocab_number && item.meaning_id !== vocabulary.meaning_id)
          .map((item) => item.meaning_id)
          .filter((meaning, index, list) => list.indexOf(meaning) === index)
        if (distractors.length < 3) throw new Error('Kategori ini membutuhkan sedikitnya empat arti kosakata yang berbeda.')
        const options = [vocabulary.meaning_id, ...distractors.sort(() => Math.random() - 0.5).slice(0, 3)]
          .sort(() => Math.random() - 0.5)
          .map((option_text) => ({ option_text, is_correct: option_text === vocabulary.meaning_id }))
        return {
          ...createQuestion(),
          question_text: `Apa arti kata "${vocabulary.word_en}"?`,
          explanation: `${vocabulary.word_en} berarti ${vocabulary.meaning_id}.`,
          options,
        }
      })
      if (!generated.length) throw new Error('Belum ada kosakata aktif pada kategori ini.')
      setEditor((current) => ({ ...current, method: 'vocabulary', questions: generated }))
      setNotice(`${generated.length} soal kosakata dibuat. Periksa dan edit soal sebelum menyimpan.`)
    } catch (generateError) {
      setError(generateError.message)
    } finally {
      setBusy(false)
    }
  }

  async function uploadQuestionImage(questionIndex, file) {
    if (!file) return
    setBusy(true)
    setError('')
    try {
      let quizId = editor.id
      if (!quizId) quizId = await saveDraft()
      if (!quizId) return
      const formData = new FormData()
      formData.append('quiz_id', String(quizId))
      formData.append('image', file)
      const response = await fetch(`${API_URL}?action=quiz_upload_image`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-Token': csrfToken },
        body: formData,
      })
      const result = await response.json()
      if (!response.ok) throw new Error(result.error || 'Gambar gagal diunggah.')
      updateQuestion(questionIndex, 'image_path', result.image_path)
    } catch (uploadError) {
      setError(uploadError.message)
    } finally {
      setBusy(false)
    }
  }

  async function createSession() {
    if (!sessionDraft) return
    setBusy(true)
    setError('')
    try {
      const result = await request('quiz_create_session', {
        method: 'POST',
        csrfToken,
        body: {
          quiz_id: sessionDraft.quiz.id,
          mode: sessionDraft.mode,
          start_at: sessionDraft.start_at,
          end_at: sessionDraft.end_at,
        },
      })
      setSessionDraft(null)
      await loadQuizzes()
      setNotice(`Room berhasil dibuat. Kode bergabung: ${result.session_code}`)
      if (result.mode === 'live') setActiveRoom({ session_id: result.session_id, is_host: true, code: result.session_code })
    } catch (createError) {
      setError(createError.message)
    } finally {
      setBusy(false)
    }
  }

  async function joinRoom(event) {
    event.preventDefault()
    setBusy(true)
    setError('')
    try {
      const result = await request('room_join', { method: 'POST', csrfToken, body: { code: roomCode } })
      if (result.mode === 'assignment') {
        window.location.href = `${BASE_URL}/${result.redirect}`
      } else if (result.mode === 'legacy-room') {
        window.location.href = `${BASE_URL}/${result.redirect}`
      } else {
        setRoomCode('')
        setRoomState(null)
        setActiveRoom({ session_id: result.session_id, is_host: false, code: result.session_code })
      }
    } catch (joinError) {
      setError(joinError.message)
    } finally {
      setBusy(false)
    }
  }

  async function hostAction(action) {
    setBusy(true)
    setError('')
    try {
      await request('live_host_action', {
        method: 'POST',
        csrfToken,
        body: { session_id: activeRoom.session_id, action },
      })
      const state = await request('room_state', { query: { session_id: activeRoom.session_id } })
      setRoomState(state)
    } catch (hostError) {
      setError(hostError.message)
    } finally {
      setBusy(false)
    }
  }

  async function submitLiveAnswer() {
    if (!answerOption || !roomState?.question) return
    setBusy(true)
    setError('')
    try {
      await request('live_answer', {
        method: 'POST',
        csrfToken,
        body: {
          session_id: activeRoom.session_id,
          question_id: roomState.question.id,
          option_id: answerOption,
        },
      })
      setAnswerOption(null)
      setNotice('Jawaban terkirim. Tunggu soal berikutnya.')
    } catch (answerError) {
      setError(answerError.message)
    } finally {
      setBusy(false)
    }
  }

  async function viewAssignmentResults(sessionId) {
    setBusy(true)
    setError('')
    try {
      setAssignmentResult(await request('assignment_leaderboard', { query: { session_id: sessionId } }))
    } catch (resultError) {
      setError(resultError.message)
    } finally {
      setBusy(false)
    }
  }

  const myQuizzes = quizzes.filter((quiz) => quiz.is_owner)
  const publicQuizzes = quizzes.filter((quiz) => !quiz.is_owner)
  const displayImage = (imagePath) => imagePath ? `${BASE_URL}/${imagePath}` : ''

  if (activeRoom && roomState) {
    const session = roomState.session
    const question = roomState.question
    const secondsLeft = question?.seconds_remaining ?? null
    return <section className="quiz-room-view">
      <div className="quiz-room-heading">
        <div><span className="eyebrow">QUIZ ROOM · {session.code}</span><h2>{session.title}</h2><p>{roomState.is_host ? 'Anda adalah host room ini.' : 'Menunggu host memulai permainan.'}</p></div>
        <button className="button button-secondary" type="button" onClick={() => { setActiveRoom(null); setRoomState(null) }}>Keluar dari tampilan room</button>
      </div>
      {session.status === 'waiting' && <div className="quiz-waiting"><h3>Menunggu pemain</h3><p>Bagikan kode <strong>{session.code}</strong> kepada peserta. Kode ini mengizinkan mereka masuk ke room privat ini.</p>{roomState.is_host && <button className="button button-primary" disabled={busy || roomState.participants.length === 0} onClick={() => hostAction('start')}>Mulai Live Quiz</button>}</div>}
      {session.status === 'running' && <div className="quiz-live-panel">
        <div className="quiz-live-status"><strong>Soal {session.current_question_position} / {session.question_count}</strong><span>{secondsLeft === null ? '' : `${secondsLeft} detik`}</span></div>
        {question && <><h3>{question.question_text}</h3>{question.image_path && <img className="quiz-question-image" src={displayImage(question.image_path)} alt="Gambar soal" />}
          {!roomState.is_host && !roomState.answered && <div className="quiz-live-options">{question.options?.map((option) => <button key={option.id} className={Number(answerOption) === Number(option.id) ? 'selected' : ''} onClick={() => setAnswerOption(option.id)}>{option.option_text}</button>)}</div>}
          {!roomState.is_host && roomState.answered && <p className="notice notice-success">Jawabanmu sudah tercatat. Tunggu soal selanjutnya.</p>}
        </>}
        {roomState.is_host && <button className="button button-primary" disabled={busy} onClick={() => hostAction('next')}>Lanjutkan soal</button>}
        {!roomState.is_host && !roomState.answered && <button className="button button-primary" disabled={busy || !answerOption} onClick={submitLiveAnswer}>Kirim jawaban</button>}
        {roomState.is_host && <button className="button button-danger" disabled={busy} onClick={() => hostAction('end')}>Akhiri kuis</button>}
      </div>}
      {session.status === 'finished' && <div className="quiz-leaderboard">
        <h3>Leaderboard</h3>
        <div className="table-wrap"><table><thead><tr><th>Peringkat</th><th>Peserta</th><th>Skor</th><th>Benar</th><th>Akurasi</th></tr></thead><tbody>
          {roomState.leaderboard.map((entry) => <tr key={`${entry.rank}-${entry.name}`}><td>{entry.rank}</td><td>{entry.name}</td><td>{entry.score}</td><td>{entry.correct_count}</td><td>{entry.accuracy_percent}%</td></tr>)}
          {!roomState.leaderboard.length && <tr><td colSpan="5">Belum ada peserta yang menyelesaikan kuis.</td></tr>}
        </tbody></table></div>
      </div>}
      <section className="quiz-participants"><h3>Peserta ({roomState.participants.length})</h3>{roomState.participants.length
        ? roomState.participants.map((participant, index) => <div key={`${participant.name}-${index}`}><span>{participant.name}</span><small>{participant.status} · {participant.score} poin</small></div>)
        : <p>Belum ada peserta.</p>}</section>
    </section>
  }

  if (activeRoom) return <section className="quiz-room-view"><p>Memuat room...</p><button className="button button-secondary" onClick={() => setActiveRoom(null)}>Kembali</button></section>

  if (editor) return <section className="quiz-builder">
    <div className="quiz-builder-heading"><div><span className="eyebrow">QUIZ STUDIO</span><h2>{editor.id ? 'Edit kuis' : 'Buat kuis'}</h2><p>Setiap soal bernilai tetap 100 poin. Waktu per soal hanya membatasi waktu menjawab.</p></div><button className="button button-secondary" onClick={() => setEditor(null)}>Kembali ke kuis</button></div>
    <div className="quiz-editor-fields">
      <label>Judul kuis<input maxLength="150" value={editor.title} onChange={(event) => setEditor({ ...editor, title: event.target.value })} placeholder="Contoh: English Basics" /></label>
      <label>Deskripsi<textarea maxLength="5000" value={editor.description || ''} onChange={(event) => setEditor({ ...editor, description: event.target.value })} placeholder="Deskripsi singkat kuis" /></label>
      <label>Kategori<select value={editor.category_id || ''} onChange={(event) => setEditor({ ...editor, category_id: event.target.value })}><option value="">Tanpa kategori</option>{categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</select></label>
      <label>Tingkat kesulitan<select value={editor.difficulty || 'beginner'} onChange={(event) => setEditor({ ...editor, difficulty: event.target.value })}><option value="beginner">Pemula</option><option value="intermediate">Menengah</option><option value="advanced">Lanjutan</option></select></label>
      <label>Visibilitas<select value={editor.visibility || 'private'} onChange={(event) => setEditor({ ...editor, visibility: event.target.value })}><option value="private">Privat — perlu kode undangan</option><option value="public">Publik — dapat ditemukan pengguna lain</option></select></label>
    </div>
    <div className="quiz-method-switch"><button className={editor.method === 'manual' ? 'selected' : ''} onClick={() => setEditor({ ...editor, method: 'manual' })}>Buat manual</button><button className={editor.method === 'vocabulary' ? 'selected' : ''} onClick={() => setEditor({ ...editor, method: 'vocabulary' })}>Dari Vocabulary</button></div>
    {editor.method === 'vocabulary' && <div className="quiz-vocab-generator">
      <label>Kategori kosakata<select value={vocabCategoryId} onChange={(event) => setVocabCategoryId(event.target.value)}><option value="">Semua kosakata aktif</option>{vocabularyCategories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</select></label>
      <label>Jumlah soal<input type="number" min="1" max="100" value={vocabQuestionCount} onChange={(event) => setVocabQuestionCount(event.target.value)} /></label>
      <button className="button button-primary" disabled={busy} onClick={generateVocabularyQuestions}>Buat soal vocabulary</button>
    </div>}
    <div className="quiz-builder-title"><h3>Soal ({editor.questions.length})</h3><button className="button button-secondary" onClick={() => setEditor({ ...editor, questions: [...editor.questions, createQuestion()] })}>+ Tambah soal</button></div>
    <div className="quiz-question-editor-list">{editor.questions.map((question, questionIndex) => <article className="quiz-question-editor" key={questionIndex}>
      <div className="quiz-question-editor-title"><strong>Soal {questionIndex + 1}</strong><div><button type="button" onClick={() => setEditor({ ...editor, questions: [...editor.questions.slice(0, questionIndex + 1), { ...question, options: question.options.map((option) => ({ ...option })) }, ...editor.questions.slice(questionIndex + 1)] })}>Duplikasi</button><button type="button" className="text-button-danger" onClick={() => setEditor({ ...editor, questions: editor.questions.filter((_, index) => index !== questionIndex) })}>Hapus</button></div></div>
      <label>Pertanyaan<textarea maxLength="5000" value={question.question_text} onChange={(event) => updateQuestion(questionIndex, 'question_text', event.target.value)} placeholder="Tulis pertanyaan..." /></label>
      {question.image_path && <img className="quiz-question-image" src={displayImage(question.image_path)} alt="Pratinjau gambar soal" />}
      <label className="quiz-image-upload">Gambar (opsional)<input type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => uploadQuestionImage(questionIndex, event.target.files?.[0])} /><small>JPG, PNG, atau WebP · maksimal 5 MB</small></label>
      <div className="quiz-option-editor">{question.options.map((option, optionIndex) => <label key={optionIndex}><span><input type="radio" name={`correct-${questionIndex}`} checked={Boolean(Number(option.is_correct))} onChange={() => question.options.forEach((_, index) => updateOption(questionIndex, index, 'is_correct', index === optionIndex ? 1 : 0))} /> Jawaban {String.fromCharCode(65 + optionIndex)}{Number(option.is_correct) ? ' · Benar' : ''}</span><input maxLength="500" value={option.option_text} onChange={(event) => updateOption(questionIndex, optionIndex, 'option_text', event.target.value)} placeholder={`Pilihan ${String.fromCharCode(65 + optionIndex)}`} /></label>)}</div>
      <div className="quiz-question-meta"><label>Durasi soal (detik)<input type="number" min="5" max="600" value={question.duration_seconds || 30} onChange={(event) => updateQuestion(questionIndex, 'duration_seconds', Number(event.target.value))} /></label><label>Poin<input value="100" readOnly aria-label="Poin tetap per jawaban benar" /></label></div>
      <label>Penjelasan jawaban<textarea maxLength="5000" value={question.explanation || ''} onChange={(event) => updateQuestion(questionIndex, 'explanation', event.target.value)} placeholder="Penjelasan untuk peserta setelah kuis" /></label>
    </article>)}</div>
    <div className="quiz-builder-actions"><button className="button button-secondary" disabled={busy} onClick={saveDraft}>Simpan draft</button><button className="button button-secondary" disabled={busy} onClick={() => setPreviewOpen(true)}>Preview</button><button className="button button-primary" disabled={busy} onClick={publishQuiz}>Publikasikan</button></div>
  </section>

  return <section className="quiz-workspace">
    <div className="quiz-workspace-heading"><div><span className="eyebrow">QUIZ STUDIO</span><h2>Kuis & aktivitas</h2><p>Buat kuis, bagikan kode, host Live Quiz, atau kerjakan assignment.</p></div><button className="button button-primary" onClick={startNewQuiz}>+ Buat kuis</button></div>
    {error && <div className="notice notice-error" role="alert">{error}<button onClick={() => setError('')} aria-label="Tutup">×</button></div>}
    {notice && <div className="notice notice-success" role="status">{notice}<button onClick={() => setNotice('')} aria-label="Tutup">×</button></div>}
    <form className="quiz-join-code" onSubmit={joinRoom}><label>Gabung dengan kode room<input value={roomCode} onChange={(event) => setRoomCode(event.target.value.toUpperCase())} maxLength="12" placeholder="Masukkan kode" /></label><button className="button button-primary" disabled={busy || !roomCode}>Gabung</button></form>
    {sessionDraft && <div className="quiz-session-modal">
      <div className="quiz-session-form"><button className="quiz-modal-close" onClick={() => setSessionDraft(null)} aria-label="Tutup">×</button><span className="eyebrow">{sessionDraft.mode === 'live' ? 'LIVE QUIZ' : 'ASSIGNMENT'}</span><h3>{sessionDraft.quiz.title}</h3>
        {sessionDraft.mode === 'assignment' && <><label>Mulai (opsional)<input type="datetime-local" value={sessionDraft.start_at} onChange={(event) => setSessionDraft({ ...sessionDraft, start_at: event.target.value })} /></label><label>Batas pengerjaan<input type="datetime-local" value={sessionDraft.end_at} onChange={(event) => setSessionDraft({ ...sessionDraft, end_at: event.target.value })} /></label></>}
        <p>Room dibuat privat. Bagikan kode kepada peserta yang diizinkan.</p><button className="button button-primary" disabled={busy || (sessionDraft.mode === 'assignment' && !sessionDraft.end_at)} onClick={createSession}>Buat {sessionDraft.mode === 'live' ? 'Live Quiz' : 'Assignment'}</button>
      </div>
    </div>}
    {assignmentResult && <div className="quiz-session-modal"><div className="quiz-session-form"><button className="quiz-modal-close" onClick={() => setAssignmentResult(null)} aria-label="Tutup">×</button><span className="eyebrow">ASSIGNMENT RESULTS</span><h3>{assignmentResult.session.title}</h3><div className="table-wrap"><table><thead><tr><th>Peringkat</th><th>Peserta</th><th>Skor</th><th>Benar</th><th>Akurasi</th></tr></thead><tbody>{assignmentResult.leaderboard.map((entry) => <tr key={`${entry.rank}-${entry.name}`}><td>{entry.rank}</td><td>{entry.name}</td><td>{entry.score}</td><td>{entry.correct_count}</td><td>{entry.accuracy_percent}%</td></tr>)}</tbody></table></div></div></div>}
    <div className="quiz-list-section"><h3>Kuis saya</h3>{myQuizzes.length
      ? <div className="quiz-card-grid">{myQuizzes.map((quiz) => <article className="quiz-library-card" key={quiz.id}><div className="quiz-library-meta"><span>{quiz.difficulty}</span><span>{quiz.visibility === 'public' ? 'Publik' : 'Privat'}</span></div><h4>{quiz.title}</h4><p>{quiz.description || 'Belum ada deskripsi.'}</p><small>{quiz.category || 'Tanpa kategori'} · {quiz.question_count} soal · {quiz.status === 'published' ? 'Terbit' : 'Draft'}</small><div className="quiz-card-actions">{quiz.status === 'published' && <><button onClick={() => setSessionDraft({ quiz, mode: 'live', start_at: '', end_at: '' })}>Host Live</button><button onClick={() => setSessionDraft({ quiz, mode: 'assignment', start_at: '', end_at: '' })}>Buat Assignment</button></>}{quiz.session_count === 0 && <button onClick={() => editQuiz(quiz.id)}>Edit</button>}<button onClick={() => duplicateQuiz(quiz.id)}>Duplikasi</button><button className="text-button-danger" onClick={() => deleteQuiz(quiz.id)}>Hapus</button></div></article>)}</div>
      : <EmptyState>Anda belum memiliki kuis. Buat kuis manual atau dari Vocabulary.</EmptyState>}</div>
    {!!publicQuizzes.length && <div className="quiz-list-section"><h3>Kuis publik</h3><div className="quiz-card-grid">{publicQuizzes.map((quiz) => <article className="quiz-library-card" key={quiz.id}><div className="quiz-library-meta"><span>{quiz.difficulty}</span><span>{quiz.creator_name}</span></div><h4>{quiz.title}</h4><p>{quiz.description || 'Belum ada deskripsi.'}</p><small>{quiz.category || 'Tanpa kategori'} · {quiz.question_count} soal</small><div className="quiz-card-actions"><button onClick={() => duplicateQuiz(quiz.id)}>Salin ke kuis saya</button></div></article>)}</div></div>}
    {!!sessions.length && <div className="quiz-list-section"><h3>Room saya</h3><div className="table-wrap"><table><thead><tr><th>Kuis</th><th>Kode</th><th>Mode</th><th>Status</th><th>Aksi</th></tr></thead><tbody>{sessions.map((session) => <tr key={session.id}><td>{session.title}</td><td>{session.session_code}</td><td>{session.mode === 'live' ? 'Live' : 'Assignment'}</td><td>{session.live_status || session.status}</td><td>{session.mode === 'live' && <button onClick={() => setActiveRoom({ session_id: Number(session.id), is_host: true, code: session.session_code })}>Buka host</button>}{session.mode === 'assignment' && <button onClick={() => viewAssignmentResults(session.id)}>Leaderboard</button>}</td></tr>)}</tbody></table></div></div>}
    {previewOpen && <div className="quiz-session-modal"><div className="quiz-preview-modal"><button className="quiz-modal-close" onClick={() => setPreviewOpen(false)} aria-label="Tutup">×</button><span className="eyebrow">PREVIEW</span><h3>{editor.title || 'Pratinjau kuis'}</h3><p>{editor.description}</p>{editor.questions.map((question, index) => <article className="quiz-preview-question" key={index}><strong>{index + 1}. {question.question_text || '(Pertanyaan belum diisi)'}</strong>{question.image_path && <img src={displayImage(question.image_path)} alt="" />}<ol type="A">{question.options.map((option, optionIndex) => <li key={optionIndex}>{option.option_text || '(Pilihan kosong)'}{Number(option.is_correct) ? ' ✓' : ''}</li>)}</ol><small>{question.duration_seconds || 30} detik · 100 poin</small></article>)}</div></div>}
  </section>
}

function speak(word) {
  if (!('speechSynthesis' in window)) {
    window.alert('Browser ini belum mendukung audio pengucapan.')
    return
  }
  window.speechSynthesis.cancel()
  const utterance = new SpeechSynthesisUtterance(word)
  utterance.lang = 'en-US'
  utterance.rate = 0.9
  window.speechSynthesis.speak(utterance)
}

function PronunciationPage({ data }) {
  const items = (data?.items || []).slice(0, 20)
  return <section className="section-block"><SectionHeading title="Listen and repeat" subtitle="Pilih kata untuk mendengar pelafalan bahasa Inggris." /><div className="pronunciation-list">{items.map((item) => <article className="pronunciation-row" key={item.id}><div><strong>{item.word_en}</strong><span>{item.meaning_id}</span></div><button className="sound-button" onClick={() => speak(item.word_en)}><Icon name="sound" /> Dengarkan</button></article>)}</div>{!items.length && <EmptyState>Belum ada kosakata aktif untuk latihan.</EmptyState>}<p className="helper-text">Audio menggunakan Speech Synthesis bawaan browser dan memerlukan dukungan browser.</p></section>
}

function ProgressPage({ data }) {
  if (!data) return <EmptyState>Data progres belum tersedia.</EmptyState>
  const stats = data.stats || {}
  return <>
    <section className="metrics-grid">
      <Metric label="Kuis selesai" value={stats.total_quiz || 0} note="Percobaan yang dikumpulkan" icon="check" />
      <Metric label="Rata-rata skor" value={`${Math.round(Number(stats.avg_score || 0))}%`} note="Persentase semua kuis" icon="chart" />
      <Metric label="Kosakata dipelajari" value={data.wordsLearned || 0} note="Kata unik ditandai dipelajari" icon="book" />
    </section>
    <section className="section-block"><SectionHeading title="Riwayat kuis" subtitle="Maksimal 10 hasil terakhir dari akunmu." />{data.history?.length ? <div className="table-wrap"><table><thead><tr><th>Nama kuis</th><th>Rentang kosakata</th><th>Skor</th><th>Tanggal</th></tr></thead><tbody>{data.history.map((item, index) => <tr key={`${item.submitted_at}-${index}`}><td>{item.title || 'Quiz'}</td><td>{item.start_number ? `${item.start_number}–${item.end_number}` : '—'}</td><td><strong>{item.score}/{item.max_score}</strong></td><td>{new Date(item.submitted_at).toLocaleDateString('id-ID')}</td></tr>)}</tbody></table></div> : <EmptyState>Belum ada kuis yang selesai.</EmptyState>}</section>
  </>
}

function ProfilePage({ form, onChange, onSubmit }) {
  return <section className="profile-panel"><div className="profile-large"><span className="large-avatar">{form.name.slice(0, 1).toUpperCase() || 'U'}</span><div><h2>{form.name || 'Profil siswa'}</h2><p>Informasi akun tersimpan di EnglishHub.</p></div></div><form className="form-stack profile-form" onSubmit={onSubmit}><label>Nama lengkap<input required maxLength={100} value={form.name} onChange={(event) => onChange({ ...form, name: event.target.value })} /></label><label>Alamat email<input type="email" required maxLength={190} value={form.email} onChange={(event) => onChange({ ...form, email: event.target.value })} /></label><button className="button button-primary">Simpan perubahan</button></form></section>
}

export default App
