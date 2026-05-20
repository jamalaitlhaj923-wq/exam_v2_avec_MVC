<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - EventHub Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body { font-family: "DM Sans", sans-serif; background:#f8fafc; color:#0f172a; }
        .font-display { font-family:"Syne", sans-serif; }
        .cap-bar { height:8px; border-radius:999px; background:#e2e8f0; overflow:hidden; }
        .cap-bar-fill { height:100%; border-radius:999px; }
        .badge { display:inline-block; padding:4px 10px; border-radius:999px; background:#dbeafe; color:#1d4ed8; font-size:12px; font-weight:700; }
        .toast { position:fixed; right:20px; bottom:20px; padding:12px 16px; border-radius:10px; background:#0f172a; color:white; box-shadow:0 20px 40px rgba(15,23,42,.18); }
        .toast.error { background:#dc2626; }
        .toast.success { background:#16a34a; }
    </style>
</head>
<body>
    <main class="max-w-6xl mx-auto px-6 py-10">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="font-display text-3xl font-extrabold">Dashboard Organisateur</h1>
                <p id="last-update" class="text-slate-500 mt-1">Chargement...</p>
            </div>
            <a href="index.html" class="px-5 py-3 rounded-xl bg-slate-900 text-white font-display font-bold">Evenements</a>
        </div>

        <section class="grid md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                <p class="text-slate-400 text-sm">Total inscrits</p>
                <p id="kpi-total" class="font-display text-4xl font-extrabold mt-2">0</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                <p class="text-slate-400 text-sm">Nouveaux 24h</p>
                <p id="kpi-new-24h" class="font-display text-4xl font-extrabold mt-2">0</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                <p class="text-slate-400 text-sm">Taux moyen</p>
                <p id="kpi-taux" class="font-display text-4xl font-extrabold mt-2">0%</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                <p class="text-slate-400 text-sm">Alertes 80%</p>
                <p id="kpi-alertes" class="font-display text-4xl font-extrabold mt-2">0</p>
            </div>
        </section>

        <section class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
            <h2 class="font-display text-xl font-bold mb-4">Top 3 evenements</h2>
            <div id="top3-list" class="space-y-3"></div>
        </section>
    </main>
    <div id="toast-container"></div>
    <script src="assets/js/app.js"></script>
    <script>document.addEventListener('DOMContentLoaded', startDashboard);</script>
</body>
</html>
