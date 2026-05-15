const RESIZABLE_SELECTOR = '[data-resizable-columns="true"]';
const MIN_COLUMN_WIDTH = 120;

const areResizableTablesEnabled = () => document.body.dataset.resizableTablesEnabled !== 'false';

const isDesktopPointer = () => window.matchMedia('(min-width: 992px)').matches;

const getLeafHeaders = (table) => {
    const head = table.tHead;

    if (!head || head.rows.length === 0) {
        return [];
    }

    return Array.from(head.rows[head.rows.length - 1].cells);
};

const ensureColgroup = (table, headerCells) => {
    let colgroup = table.querySelector('colgroup');

    if (!colgroup) {
        colgroup = document.createElement('colgroup');
        table.insertBefore(colgroup, table.firstChild);
    }

    while (colgroup.children.length < headerCells.length) {
        colgroup.appendChild(document.createElement('col'));
    }

    while (colgroup.children.length > headerCells.length) {
        colgroup.removeChild(colgroup.lastChild);
    }

    return Array.from(colgroup.children);
};

const syncTableWidth = (table, wrapper, columns) => {
    const totalWidth = columns.reduce((sum, col) => sum + parseFloat(col.style.width || '0'), 0);
    const wrapperWidth = wrapper.clientWidth;
    table.style.width = `${Math.max(totalWidth, wrapperWidth)}px`;
    table.style.minWidth = `${wrapperWidth}px`;
};

const applyInitialWidths = (table, wrapper, headers, columns) => {
    headers.forEach((header, index) => {
        const width = Math.max(Math.ceil(header.getBoundingClientRect().width), MIN_COLUMN_WIDTH);
        columns[index].style.width = `${width}px`;
    });

    syncTableWidth(table, wrapper, columns);
};

const makeHeaderResizable = (table, wrapper, header, column, columns) => {
    if (header.dataset.resizeReady === 'true') {
        return;
    }

    header.dataset.resizeReady = 'true';
    header.classList.add('catalog-table__head-cell');

    const handle = document.createElement('button');
    handle.type = 'button';
    handle.className = 'catalog-table__resize-handle';
    handle.setAttribute('aria-label', `Redimensionar columna ${header.textContent?.trim() || ''}`);

    handle.addEventListener('mousedown', (event) => {
        if (!isDesktopPointer()) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const startX = event.clientX;
        const initialWidth = parseFloat(column.style.width || `${header.getBoundingClientRect().width}`);

        document.body.classList.add('catalog-resize-active');

        const onMouseMove = (moveEvent) => {
            const delta = moveEvent.clientX - startX;
            const nextWidth = Math.max(MIN_COLUMN_WIDTH, initialWidth + delta);
            column.style.width = `${nextWidth}px`;
            syncTableWidth(table, wrapper, columns);
        };

        const onMouseUp = () => {
            document.body.classList.remove('catalog-resize-active');
            window.removeEventListener('mousemove', onMouseMove);
            window.removeEventListener('mouseup', onMouseUp);
        };

        window.addEventListener('mousemove', onMouseMove);
        window.addEventListener('mouseup', onMouseUp);
    });

    header.appendChild(handle);
};

const initResizableTable = (container) => {
    const wrapper = container.querySelector('.table-responsive');
    const table = container.querySelector('table');

    if (!wrapper || !table) {
        return;
    }

    const headers = getLeafHeaders(table);

    if (headers.length < 2) {
        return;
    }

    const columns = ensureColgroup(table, headers);
    applyInitialWidths(table, wrapper, headers, columns);

    headers.forEach((header, index) => {
        makeHeaderResizable(table, wrapper, header, columns[index], columns);
    });

    if (container.dataset.resizeObserverAttached === 'true') {
        return;
    }

    container.dataset.resizeObserverAttached = 'true';

    const observer = new ResizeObserver(() => {
        syncTableWidth(table, wrapper, columns);
    });

    observer.observe(wrapper);
};

const initResizableColumns = () => {
    if (!areResizableTablesEnabled()) {
        return;
    }

    document.querySelectorAll(RESIZABLE_SELECTOR).forEach(initResizableTable);
};

document.addEventListener('DOMContentLoaded', initResizableColumns);