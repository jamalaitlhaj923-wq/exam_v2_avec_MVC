<h1>Evenements</h1>
<p class="muted"><?= (int)$total ?> evenement(s) trouves via EventModel, sans requete PDO dans la vue.</p>

<div class="grid">
    <?php foreach ($events as $event): ?>
        <?php
        $capacity = max(1, (int)$event['capacity']);
        $registered = (int)$event['registered_count'];
        $pct = min(100, (int)round($registered / $capacity * 100));
        ?>
        <article class="card">
            <span class="badge"><?= htmlspecialchars($event['category'], ENT_QUOTES, 'UTF-8') ?></span>
            <h2><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="muted"><?= date('d/m/Y H:i', strtotime($event['event_date'])) ?></p>
            <p><?= htmlspecialchars($event['location'], ENT_QUOTES, 'UTF-8') ?></p>
            <p><?= htmlspecialchars($event['description'], ENT_QUOTES, 'UTF-8') ?></p>
            <strong>Capacite : <?= $registered ?> / <?= $capacity ?></strong>
            <div class="progress" aria-label="Remplissage">
                <span style="width: <?= $pct ?>%"></span>
            </div>
        </article>
    <?php endforeach; ?>
</div>
