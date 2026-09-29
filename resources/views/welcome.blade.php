<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Web Architect') }} - Engine</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://bunny.net" rel="stylesheet" />

    <style>
        body {
            font-family: 'Figtree', sans-serif;
            background-color: #151922;
            color: #ffffff;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 40px;
            background: #1e222d;
            border-bottom: 1px solid #2d3446;
        }
        .brand {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            max-width: 1200px;
            margin: 0 auto;
            box-sizing: border-box;
        }
        .hero {
            text-align: center;
            max-width: 800px;
            margin-bottom: 60px;
        }
        .hero h1 {
            font-size: 48px;
            font-weight: 700;
            margin: 0 0 20px 0;
            line-height: 1.15;
            letter-spacing: -1px;
            background: linear-gradient(135deg, #ffffff 0%, #a4b1cd 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero p {
            color: #7885a0;
            font-size: 18px;
            line-height: 1.6;
            margin: 0 0 35px 0;
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            width: 100%;
            margin-bottom: 40px;
        }
        .feature-card {
            background: #1e222d;
            padding: 30px;
            border-radius: 10px;
            border: 1px solid #2d3446;
            transition: transform 0.2s, border-color 0.2s;
        }
        .feature-card:hover {
            transform: translateY(-2px);
            border-color: #3b82f6;
        }
        .feature-card h3 {
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 12px 0;
            color: #ffffff;
        }
        .feature-card p {
            font-size: 14px;
            color: #7885a0;
            margin: 0;
            line-height: 1.5;
        }
        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        .btn {
            padding: 14px 32px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
        }
        .btn-primary {
            background-color: #3b82f6;
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: #2563eb;
        }
        .btn-secondary {
            background-color: transparent;
            color: #a4b1cd;
            border: 1px solid #4e566d;
        }
        .btn-secondary:hover {
            background-color: #2d3446;
            color: #ffffff;
        }
        .footer {
            text-align: center;
            padding: 25px;
            color: #4e566d;
            font-size: 13px;
            border-top: 1px solid #1e222d;
            background: #151922;
        }
    </style>
</head>
<body>

    <header class="navbar">
        <div class="brand">Web Architect</div>
        <div>
            @auth
                <a href="{{ url('/dashboard') }}" class="btn btn-secondary" style="padding: 8px 18px; font-size: 14px;">Do Workspace &rarr;</a>
            @else
                <a href="{{ route('login') }}" style="color: #a4b1cd; text-decoration: none; margin-right: 20px; font-size: 14px; font-weight: 600;">Prihlásiť sa</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="btn btn-primary" style="padding: 8px 18px; font-size: 14px;">Registrácia</a>
                @endif
            @endauth
        </div>
    </header>

    <main class="main-content">
        <section class="hero">
            <h1>Vizuálny CMS Engine bez limitov kódu</h1>
            <p>Navrhuj, upravuj a spravuj rozloženie stránok priamo v prehliadači pomocou reaktívnych komponentov. Spojenie absolútnej vizuálnej slobody s čistou architektúrou tvojho backendu.</p>
            
            <div class="btn-group">
                @auth
                    <a href="{{ url('/dashboard') }}" class="btn btn-primary">Otvoriť Workspace</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary">Začať budovať</a>
                    <a href="https://github.com" target="_blank" class="btn btn-secondary">Dokumentácia</a>
                @endauth
            </div>
        </section>

        <section class="features-grid">
            <div class="feature-card">
                <h3>Visual Drag & Drop</h3>
                <p>Usporiadaj sekcie, moduly a prvky jednoduchým potiahnutím. Žiadne zložité prepisovanie štruktúry v šablónach.</p>
            </div>
            <div class="feature-card">
                <h3>Vlastné komponenty</h3>
                <p>Engine plne spolupracuje s tvojimi existujúcimi Blade a AlpineJS komponentmi. Nestrácaš žiadnu kontrolu nad kódom.</p>
            </div>
            <div class="feature-card">
                <h3>Živý Canvas náhľad</h3>
                <p>Upravuj rozloženie a okamžite sleduj zmeny na pracovnej ploche. Rozmery bočných panelov si prispôsobíš podľa seba.</p>
            </div>
        </section>
    </main>

    <footer class="footer">
        &copy; {{ date('Y') }} Web Architect Engine. Vytvorené pre vizuálny vývoj.
    </footer>

</body>
</html>
