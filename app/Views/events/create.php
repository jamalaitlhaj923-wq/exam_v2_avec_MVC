<section class="panel">
    <h1>Creer un evenement</h1>
    <form method="post" action="index.php?route=events/store">
        <label for="title">Titre</label>
        <input id="title" name="title" required>

        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4" required></textarea>

        <label for="date">Date</label>
        <input id="date" name="date" type="datetime-local" required>

        <label for="location">Lieu</label>
        <input id="location" name="location" required>

        <label for="capacity">Capacite</label>
        <input id="capacity" name="capacity" type="number" min="1" required>

        <label for="category">Categorie</label>
        <select id="category" name="category" required>
            <option value="tech">Tech</option>
            <option value="design">Design</option>
            <option value="business">Business</option>
            <option value="science">Science</option>
        </select>

        <label for="organizer_email">Email organisateur</label>
        <input id="organizer_email" name="organizer_email" type="email" required>

        <button type="submit">Creer en MVC</button>
    </form>
</section>
