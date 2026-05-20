'use strict';

const STATE = {
    currentTab: 'all',
    dashInterval: null,
    debounceTimer: null,
    selectedEvent: null,
};

const CATEGORY_COLORS = {
    tech: { bg: '#DBEAFE', text: '#1D4ED8', primary: '#2563EB' },
    design: { bg: '#EDE9FE', text: '#6D28D9', primary: '#7C3AED' },
    business: { bg: '#FEF3C7', text: '#B45309', primary: '#EA580C' },
    science: { bg: '#DCFCE7', text: '#15803D', primary: '#16A34A' },
};

async function loadEvents() {
    const keyword = document.getElementById('search-input')?.value ?? '';
    const category = document.getElementById('filter-category')?.value
        ?? document.getElementById('filter-cat')?.value
        ?? '';
    const hasPlaces = document.getElementById('filter-places')?.value === '1';

    showSkeletons();

    try {
        const response = await fetch('api/events.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ keyword, category, has_places: hasPlaces, tab: STATE.currentTab }),
        });

        if (!response.ok) throw new Error('HTTP ' + response.status);
        const data = await response.json();

        if (data.success) {
            renderEventCards(data.data);
        } else {
            showGridError(data.error ?? 'Erreur inconnue.');
        }
    } catch (err) {
        console.error('[loadEvents]', err);
        showToast('Impossible de charger les evenements.', 'error');
        showGridError('Erreur de connexion au serveur.');
    }
}

async function registerToEvent(eventId, name, email) {
    setButtonLoading('btn-register', true, 'Inscription en cours...');

    try {
        const response = await fetch('events/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ event_id: eventId, name, email }),
        });

        const data = await response.json();

        if (data.success) {
            closeRegisterModal?.();
            showToast('Inscription reussie ! Ticket envoye par email.', 'success');

            const bar = document.getElementById('bar-' + eventId);
            const places = document.getElementById('places-' + eventId);
            const btn = document.getElementById('btn-' + eventId);

            if (bar) bar.style.width = data.capacity_pct + '%';
            if (places) places.textContent = data.registered + ' / ' + data.capacity;

            if (data.is_full && btn) {
                btn.disabled = true;
                btn.textContent = 'Complet';
                btn.style.background = '#94A3B8';
            }

            if (data.alert_sent) {
                showToast("Alerte 80% envoyee a l'organisateur.", 'info');
            }
        } else {
            showToast(data.error ?? "Erreur lors de l'inscription.", 'error');
        }
    } catch (err) {
        console.error('[registerToEvent]', err);
        showToast('Erreur reseau. Veuillez reessayer.', 'error');
    } finally {
        setButtonLoading('btn-register', false, "S'inscrire");
    }
}

function debounceSearch() {
    clearTimeout(STATE.debounceTimer);
    STATE.debounceTimer = setTimeout(loadEvents, 400);
}

function startDashboard() {
    if (STATE.dashInterval) clearInterval(STATE.dashInterval);
    fetchDashboardStats();
    STATE.dashInterval = setInterval(fetchDashboardStats, 30000);
}

async function fetchDashboardStats() {
    try {
        const response = await fetch('api/stats.php');
        if (!response.ok) throw new Error('HTTP ' + response.status);

        const data = await response.json();
        if (!data.success) throw new Error(data.error);

        animateCounter('kpi-total', Number(data.summary.total_registered ?? 0));
        animateCounter('kpi-new-24h', Number(data.summary.new_last_24h ?? 0));
        animateCounter('kpi-alertes', Number(data.summary.alert_count ?? 0));

        const taux = document.getElementById('kpi-taux');
        if (taux) taux.textContent = (data.summary.avg_fill_pct ?? 0) + '%';

        renderTop3(data.top3);

        const last = document.getElementById('last-update');
        if (last) last.textContent = 'Mis a jour a ' + new Date().toLocaleTimeString('fr-FR');
    } catch (err) {
        console.error('[fetchDashboardStats]', err);
        clearInterval(STATE.dashInterval);
        setTimeout(startDashboard, 10000);
        showToast('Erreur de chargement du dashboard. Nouvelle tentative dans 10s.', 'error');
    }
}

async function fetchEventPreview(eventId) {
    try {
        const res = await fetch(`api/events.php?preview=1&id=${eventId}`);
        const data = await res.json();
        if (data.success) renderPreviewPanel(data.data);
    } catch (e) {
        console.warn('[preview]', e);
    }
}

function renderEventCards(events) {
    const grid = document.getElementById('events-grid');
    if (!grid) return;

    if (!events || events.length === 0) {
        grid.innerHTML = '<div class="col-span-3 text-center py-16"><p class="font-display font-bold text-slate-600 text-lg">Aucun evenement trouve</p><p class="text-slate-400 text-sm mt-2">Modifiez vos criteres de recherche</p></div>';
        return;
    }

    grid.innerHTML = events.map((e) => {
        const pct = parseInt(e.fill_percentage, 10) || 0;
        const isFull = Number(e.available_places) <= 0;
        const isWarn = pct >= 80 && !isFull;
        const colors = CATEGORY_COLORS[e.category] || { bg: '#F1F5F9', text: '#334155', primary: '#64748B' };
        const barColor = isFull ? '#DC2626' : isWarn ? '#F59E0B' : colors.primary;

        return `
        <div class="event-card bg-white rounded-2xl border border-slate-200 overflow-hidden flex flex-col shadow-sm"
             data-event-id="${e.id}" onmouseenter="fetchEventPreview(${e.id})">
            <div class="h-2" style="background:${colors.primary}"></div>
            <div class="p-5 flex flex-col flex-1">
                <div class="flex items-start gap-2 mb-3 flex-wrap">
                    <span class="badge" style="background:${colors.bg};color:${colors.text}">${e.category}</span>
                    ${isFull ? '<span class="badge" style="background:#FEE2E2;color:#DC2626">Complet</span>' : ''}
                    ${isWarn ? '<span class="badge" style="background:#FEF3C7;color:#B45309">Quasi plein</span>' : ''}
                </div>
                <h3 class="font-display font-bold text-base text-slate-900 mb-1 leading-snug">${e.title}</h3>
                <p class="text-xs text-slate-500 mb-1">${formatDate(e.event_date)}</p>
                <p class="text-xs text-slate-500 mb-3">${e.location}</p>
                <p class="text-xs text-slate-600 leading-relaxed flex-1">${e.description}</p>
                <div class="mt-4">
                    <div class="flex justify-between text-xs font-display font-bold mb-1">
                        <span class="text-slate-400">Capacite</span>
                        <span style="color:${barColor}" id="places-${e.id}">${e.registered_count} / ${e.capacity}</span>
                    </div>
                    <div class="cap-bar">
                        <div class="cap-bar-fill" id="bar-${e.id}" style="width:${pct}%; background:${barColor}"></div>
                    </div>
                    ${!isFull ? `<p class="text-xs text-slate-400 mt-1">${e.available_places} place(s) restante(s)</p>` : ''}
                </div>
                <button id="btn-${e.id}" ${isFull ? 'disabled' : `onclick="openRegisterModal?.(${e.id})"`}
                    class="mt-4 w-full py-2.5 rounded-xl font-display font-bold text-xs text-white tracking-wide ${isFull ? 'opacity-40 cursor-not-allowed' : 'hover:opacity-90 transition'}"
                    style="background:${isFull ? '#94A3B8' : colors.primary}">
                    ${isFull ? 'Complet' : "S'inscrire ->"}
                </button>
            </div>
        </div>`;
    }).join('');
}

function renderTop3(top3) {
    const el = document.getElementById('top3-list') || document.getElementById('top-list');
    if (!el) return;
    el.innerHTML = (top3 || []).map((e, i) => {
        const pct = Number(e.fill_pct ?? 0);
        return `<div class="flex items-center gap-4 p-3 rounded-xl bg-slate-50">
            <span class="font-display font-black text-2xl text-slate-200">0${i + 1}</span>
            <div class="flex-1">
                <p class="font-display font-bold text-sm text-slate-900 mb-1">${e.title}</p>
                <div class="cap-bar"><div class="cap-bar-fill" style="width:${pct}%;background:${pct >= 80 ? '#F59E0B' : '#2563EB'}"></div></div>
            </div>
            <span class="badge">${pct}%</span>
        </div>`;
    }).join('');
}

function renderPreviewPanel(data) {
    let panel = document.getElementById('event-preview-panel');
    if (!panel) {
        panel = document.createElement('aside');
        panel.id = 'event-preview-panel';
        panel.className = 'fixed right-4 bottom-4 z-50 w-80 bg-white border border-slate-200 rounded-xl shadow-xl p-4 text-sm';
        document.body.appendChild(panel);
    }
    const event = data.event;
    panel.innerHTML = `
        <p class="font-display font-bold text-slate-900 mb-2">${event.title}</p>
        <p class="text-slate-500 mb-3">${event.registered_count} inscrits - ${event.fill_percentage}%</p>
        <p class="font-bold text-xs text-slate-400 mb-1">Derniers inscrits</p>
        ${(data.latest || []).map((r) => `<p class="text-slate-600">${r.masked_name} - ${r.registered_at}</p>`).join('') || '<p class="text-slate-400">Aucun inscrit recent</p>'}
    `;
}

function showSkeletons(count = 3) {
    const grid = document.getElementById('events-grid');
    if (!grid) return;
    grid.innerHTML = Array.from({ length: count }, () => '<div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm"><div class="skeleton h-5 w-3/4 mb-2"></div><div class="skeleton h-3 w-1/2 mb-4"></div><div class="skeleton h-24 w-full"></div></div>').join('');
}

function showGridError(message) {
    const grid = document.getElementById('events-grid');
    if (grid) grid.innerHTML = `<div class="col-span-3 text-center py-16"><p class="font-display font-bold text-red-600">${message}</p><button onclick="loadEvents()" class="mt-4 px-6 py-2 rounded-lg text-sm font-display font-bold text-white" style="background:#2563eb">Reessayer</button></div>`;
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.cssText = 'opacity:0; transform:translateX(120%); transition:all .3s ease;';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

function setButtonLoading(buttonId, loading, loadingText = 'Chargement...') {
    const btn = document.getElementById(buttonId);
    if (!btn) return;
    btn.disabled = loading;
    if (loading) {
        btn.dataset.originalText = btn.textContent;
        btn.innerHTML = `<span class="spinner"></span> ${loadingText}`;
    } else {
        btn.innerHTML = btn.dataset.originalText || loadingText;
    }
}

function animateCounter(elementId, target) {
    const el = document.getElementById(elementId);
    if (!el) return;
    const start = parseInt(el.textContent, 10) || 0;
    const diff = target - start;
    const steps = 24;
    let step = 0;
    const timer = setInterval(() => {
        step++;
        el.textContent = Math.round(start + diff * (step / steps));
        if (step >= steps) {
            el.textContent = target;
            clearInterval(timer);
        }
    }, 20);
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('fr-FR', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).replace(':', 'h');
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('events-grid')) loadEvents();
});
