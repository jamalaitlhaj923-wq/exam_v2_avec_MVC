<h1>Dashboard organisateur</h1>

<div class="grid">
    <section class="panel">
        <h2><?= (int)$summary['total_events'] ?></h2>
        <p class="muted">Evenements</p>
    </section>
    <section class="panel">
        <h2><?= (int)$summary['total_registered'] ?></h2>
        <p class="muted">Inscriptions</p>
    </section>
    <section class="panel">
        <h2><?= (int)$summary['new_last_24h'] ?></h2>
        <p class="muted">Nouvelles 24h</p>
    </section>
    <section class="panel">
        <h2><?= (int)$summary['alert_count'] ?></h2>
        <p class="muted">Alertes 80%</p>
    </section>
</div>

<h2>Top 3 remplissage</h2>
<table>
    <thead>
    <tr>
        <th>Evenement</th>
        <th>Inscrits</th>
        <th>Remplissage</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($top3 as $event): ?>
        <tr>
            <td><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= (int)$event['reg'] ?></td>
            <td><?= (int)$event['fill_pct'] ?>%</td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
