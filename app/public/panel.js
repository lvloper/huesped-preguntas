(() => {
    'use strict';
    const $ = (id) => document.getElementById(id);
    let page = 1, answeredPage = 1, discardedPage = 1, received, selected = [], answered, discarded, discardedCount = 0, loading = false, version = 0;
    const busy = new Set(), pending = new Map();
    let interacting = false, deferredRender = false;
    document.addEventListener('pointerdown', () => { interacting = true; });
    const release = () => { interacting = false; if (deferredRender) { deferredRender = false; setTimeout(render, 0); } };
    document.addEventListener('pointerup', release);
    document.addEventListener('pointercancel', release);
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const report = (message = '') => { $('error').textContent = message; $('error').hidden = !message; };
    async function request(url, options = {}) {
        const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token }, ...options });
        if (!response.ok) throw new Error(response.status === 401 || response.status === 419 ? 'Tu sesión venció. Volvé a iniciar sesión para continuar.' : 'No pudimos actualizar las preguntas. Intentá nuevamente.');
        return response.status === 204 ? null : response.json();
    }
    function text(tag, className, content) { const el = document.createElement(tag); el.className = className; el.textContent = content; return el; }
    function card(q, type) {
        const el = document.createElement('article'); el.className = 'card'; el.dataset.id = q.id;
        const heading = text('div', 'card-header', '');
        const person = text('p', 'person', q.name); person.append(text('span', 'email', q.email)); heading.append(person);
        if (type !== 'discarded') {
            const discard = text('button', 'discard', ''); discard.type = 'button'; discard.dataset.action = 'discard';
            discard.setAttribute('aria-label', `Descartar pregunta de ${q.name}`); discard.title = 'Descartar pregunta';
            discard.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M9 6V4h6v2M5 6l1 14h12l1-14M10 10v6M14 10v6"/></svg>';
            discard.disabled = busy.has(q.id) || pending.has(q.id);
            discard.onclick = () => mutate(q.id, {}, 'DELETE'); heading.append(discard);
        }
        el.append(heading, text('p', 'question', q.question));
        const footer = text('div', 'card-footer', '');
        const date = new Date(type === 'discarded' ? q.deleted_at : q.created_at); footer.append(text('span', 'date', Number.isNaN(date.getTime()) ? '' : `${type === 'discarded' ? 'Descartada · ' : ''}${date.toLocaleString('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}`));
        if (type !== 'answered' && type !== 'discarded') {
            const button = text('button', '', ''); button.type = 'button'; button.dataset.action = type === 'received' ? 'select' : 'answer';
            if (type === 'received') { button.textContent = q.selected ? '✓ Seleccionada' : 'Seleccionar'; button.setAttribute('aria-pressed', String(Boolean(q.selected))); button.disabled = busy.has(q.id) || pending.has(q.id) || Boolean(q.answered); button.onclick = () => mutate(q.id, { selected: !q.selected }); }
            else if (pending.has(q.id)) { el.classList.add('pending'); button.className = 'undo'; button.textContent = 'Deshacer · respondida'; button.disabled = pending.get(q.id).saving; button.onclick = () => { clearTimeout(pending.get(q.id).timer); pending.delete(q.id); render(); }; }
            else { button.textContent = 'Marcar respondida'; button.disabled = busy.has(q.id); button.onclick = () => markAnswered(q); }
            footer.append(button);
        }
        el.append(footer); return el;
    }
    function list(id, questions, type, empty) {
        const host = $(id), focused = document.activeElement;
        const signature = JSON.stringify(questions.map(q => [q, busy.has(q.id), pending.has(q.id), pending.get(q.id)?.saving]));
        if (host.dataset.signature === signature) return;
        host.dataset.signature = signature;
        const focusId = host.contains(focused) ? focused.closest('[data-id]')?.dataset.id : null;
        const focusAction = focused?.dataset.action;
        host.replaceChildren(...questions.map(q => card(q, type)));
        if (!questions.length) host.append(text('p', 'empty', empty));
        if (focusId) Array.from(host.querySelectorAll('[data-id]')).find(el => el.dataset.id === focusId)?.querySelector(`button[data-action="${focusAction}"]`)?.focus({ preventScroll: true });
    }
    function pagination(id, data, change) {
        const host = $(id); host.replaceChildren(); if (!data || data.last_page < 2) return;
        const previous = text('button', '', '← Anterior'), next = text('button', '', 'Siguiente →');
        previous.disabled = data.current_page <= 1; next.disabled = data.current_page >= data.last_page;
        previous.onclick = () => change(data.current_page - 1); next.onclick = () => change(data.current_page + 1);
        host.append(previous, text('span', '', `Página ${data.current_page} de ${data.last_page}`), next);
    }
    function render() {
        if (interacting) { deferredRender = true; return; }
        if (received) { list('received-list', received.data, 'received', 'Todavía no hay preguntas recibidas.'); $('received-count').textContent = received.total; pagination('received-pages', received, n => { page = n; version++; refresh(); }); }
        const active = [...pending.values()].map(p => p.question).concat(selected.filter(q => !pending.has(q.id)));
        list('selected-list', active, 'selected', 'Las preguntas que selecciones aparecerán acá.'); $('selected-count').textContent = active.length;
        if (answered) { list('answered-list', answered.data, 'answered', 'Todavía no hay preguntas respondidas.'); pagination('answered-pages', answered, n => { answeredPage = n; version++; refresh(); }); }
        $('discarded-count').textContent = discardedCount;
        if (discarded) { list('discarded-list', discarded.data, 'discarded', 'Todavía no hay preguntas descartadas.'); pagination('discarded-pages', discarded, n => { discardedPage = n; version++; refresh(); }); }
    }
    async function refresh() {
        if (loading) return; loading = true; const start = version, currentPage = page, currentAnsweredPage = answeredPage, currentDiscardedPage = discardedPage;
        try {
            const data = await request(`/panel/data?page=${currentPage}`);
            let archive, trash;
            if (!$('answered-section').hidden) archive = await request(`/panel/data?answered=1&page=${currentAnsweredPage}`);
            if (!$('discarded-section').hidden) trash = await request(`/panel/data?discarded=1&page=${currentDiscardedPage}`);
            if (start !== version || busy.size) return;
            const nextPage = Math.min(currentPage, data.received.last_page), nextAnsweredPage = archive ? Math.min(currentAnsweredPage, archive.answered.last_page) : answeredPage, nextDiscardedPage = trash ? Math.min(currentDiscardedPage, trash.discarded.last_page) : discardedPage;
            if (page !== nextPage || answeredPage !== nextAnsweredPage || discardedPage !== nextDiscardedPage) { page = nextPage; answeredPage = nextAnsweredPage; discardedPage = nextDiscardedPage; version++; return; }
            received = data.received; selected = data.selected; discardedCount = data.discarded_count;
            if (archive) answered = archive.answered;
            if (trash) discarded = trash.discarded;
            render(); $('connection').textContent = 'Actualizado · actualización automática activa'; report();
        } catch (error) { report(error.message); $('connection').textContent = 'Esperando conexión'; }
        finally { loading = false; if (start !== version) refresh(); }
    }
    async function mutate(id, values, method = 'PATCH') {
        if (busy.has(id)) return; busy.add(id); version++; render();
        let failure;
        try {
            await request(`/panel/questions/${id}`, { method, body: JSON.stringify(values) }); report();
        } catch (error) { failure = error.message; }
        finally { busy.delete(id); version++; await refresh(); render(); if (failure) report(failure); }
    }
    function markAnswered(q) {
        if (pending.has(q.id)) return;
        const state = { question: q, saving: false, timer: null }; pending.set(q.id, state);
        state.timer = setTimeout(async () => {
            state.saving = true; version++; render();
            try {
                await request(`/panel/questions/${q.id}`, { method: 'PATCH', body: JSON.stringify({ answered: true }) });
                selected = selected.filter(item => item.id !== q.id);
                if (received) received.data = received.data.map(item => item.id === q.id ? { ...item, answered: true } : item);
                pending.delete(q.id); version++; render(); await refresh();
            } catch (error) { pending.delete(q.id); report(error.message); render(); }
        }, 3000); render();
    }
    $('show-answered').onclick = () => { const open = $('answered-section').hidden; $('answered-section').hidden = !open; $('show-answered').setAttribute('aria-expanded', String(open)); $('show-answered').textContent = open ? 'Ocultar preguntas respondidas' : 'Ver preguntas respondidas'; version++; if (open) refresh(); };
    $('show-discarded').onclick = () => { const open = $('discarded-section').hidden; $('discarded-section').hidden = !open; $('show-discarded').setAttribute('aria-expanded', String(open)); version++; if (open) refresh(); };
    refresh(); setInterval(() => { if (!document.hidden) refresh(); }, Math.max(1000, Number(document.body.dataset.pollMs) || 5000));
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
