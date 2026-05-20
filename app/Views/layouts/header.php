<?php $pageTitle = $title ?? 'EventHub Pro'; ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - MVC</title>
    <style>
        :root { --navy: #0f1f3d; --blue: #2563eb; --orange: #f59e0b; --line: #dbe3ef; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; color: var(--navy); background: #eef3f9; }
        header { background: white; border-bottom: 1px solid var(--line); padding: 18px 7%; display: flex; justify-content: space-between; align-items: center; }
        header strong { font-size: 22px; }
        nav a { color: var(--navy); text-decoration: none; margin-left: 20px; font-weight: 700; }
        main { width: min(1180px, 86vw); margin: 34px auto; }
        .panel { background: white; border: 1px solid var(--line); border-radius: 8px; padding: 24px; box-shadow: 0 8px 24px rgba(15, 31, 61, .06); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 22px; }
        .card { background: white; border: 1px solid var(--line); border-radius: 8px; padding: 22px; border-top: 8px solid var(--blue); }
        .muted { color: #64748b; }
        .badge { display: inline-block; padding: 6px 12px; border-radius: 999px; background: #dbeafe; color: var(--blue); font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .progress { height: 8px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
        .progress span { display: block; height: 100%; background: var(--blue); }
        label { display: block; margin: 14px 0 6px; font-weight: 700; }
        input, textarea, select { width: 100%; padding: 12px; border: 1px solid #bfccdc; border-radius: 8px; font: inherit; }
        button, .button { display: inline-block; margin-top: 18px; padding: 12px 18px; border: 0; border-radius: 8px; background: var(--blue); color: white; font-weight: 700; text-decoration: none; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 12px; border-bottom: 1px solid var(--line); text-align: left; }
        th { background: var(--navy); color: white; }
    </style>
</head>
<body>
<header>
    <strong>EventHub <span style="color: var(--orange)">Pro</span> MVC</strong>
    <nav>
        <a href="index.php?route=events">Evenements</a>
        <a href="index.php?route=events/create">Creer</a>
        <a href="index.php?route=dashboard">Dashboard</a>
        <a href="../index.html">Frontend original</a>
    </nav>
</header>
<main>
