const { createApp, ref, computed, onMounted, onBeforeUnmount, watch, nextTick } = Vue;
const POLLING_INTERVAL_MS = 5_000;

createApp({
    setup() {
        const requests = ref([]);
        const selected = ref(null);
        const loading = ref(false);
        const error = ref('');
        const page = ref(1);
        const perPage = ref(10);
        const total = ref(0);
        const lastPage = ref(1);
        const currentYear = new Date().getFullYear();
        let intervalId = null;

        const setError = (message) => {
            error.value = message;
            if (!message) {
                return;
            }

            window.clearTimeout(setError.timeoutId);
            setError.timeoutId = window.setTimeout(() => {
                error.value = '';
            }, 5000);
        };
        setError.timeoutId = null;

        const requestJson = async (url, options = {}) => {
            const response = await fetch(url, options);

            if (!response.ok) {
                let detail = '';
                try {
                    const payload = await response.json();
                    detail = payload.message || response.statusText;
                } catch (error) {
                    detail = response.statusText;
                }

                throw new Error(detail || 'Request failed');
            }

            return response.json();
        };

        const fetchRequests = async () => {
            try {
                const url = new URL('/api/requests', window.location.origin);
                url.searchParams.set('page', String(page.value));
                url.searchParams.set('per_page', String(perPage.value));

                const payload = await requestJson(url.toString());
                requests.value = payload.data || [];
                total.value = payload.meta?.total ?? requests.value.length;
                lastPage.value = payload.meta?.last_page ?? Math.max(1, Math.ceil((total.value || 1) / perPage.value));

                if (page.value > lastPage.value && lastPage.value > 0) {
                    page.value = lastPage.value;
                    return fetchRequests();
                }

                if (!requests.value.length) {
                    selected.value = null;
                    return;
                }

                if (selected.value) {
                    const current = requests.value.find((item) => item.id === selected.value.id);
                    if (current) {
                        selected.value = current;
                        return;
                    }
                }

                await select(requests.value[0].id, { silent: true });
            } catch (err) {
                setError(err.message);
            }
        };

        const select = async (id, options = { silent: false }) => {
            const silent = options?.silent ?? false;

            try {
                if (!silent) {
                    loading.value = true;
                }
                const payload = await requestJson(`/api/requests/${id}`);
                selected.value = payload.data;
            } catch (err) {
                setError(err.message);
            } finally {
                if (!silent) {
                    loading.value = false;
                }
            }
        };

        const refresh = async () => {
            loading.value = true;
            try {
                await fetchRequests();
            } finally {
                loading.value = false;
            }
        };

        const deleteOne = async () => {
            if (!selected.value) {
                return;
            }

            const confirmDelete = window.confirm('Delete this request permanently?');
            if (!confirmDelete) {
                return;
            }

            loading.value = true;
            try {
                await requestJson(`/api/requests/${selected.value.id}`, { method: 'DELETE' });
                await fetchRequests();
            } catch (err) {
                setError(err.message);
            } finally {
                loading.value = false;
            }
        };

        const deleteAll = async () => {
            if (!requests.value.length) {
                return;
            }

            const confirmDelete = window.confirm('Delete all captured requests? This cannot be undone.');
            if (!confirmDelete) {
                return;
            }

            loading.value = true;
            try {
                await requestJson('/api/requests', { method: 'DELETE' });
                page.value = 1;
                await fetchRequests();
            } catch (err) {
                setError(err.message);
            } finally {
                loading.value = false;
            }
        };

        const formatDate = (value) => {
            if (!value) {
                return '';
            }

            try {
                return new Intl.DateTimeFormat(undefined, {
                    dateStyle: 'medium',
                    timeStyle: 'medium',
                }).format(new Date(value));
            } catch (error) {
                return value;
            }
        };

        const formatDisplay = (value) => {
            if (value === null || value === undefined) {
                return {
                    isJson: false,
                    text: '(empty)',
                };
            }

            if (typeof value === 'string') {
                const trimmed = value.trim();
                if (trimmed === '') {
                    return {
                        isJson: false,
                        text: '(empty)',
                    };
                }

                try {
                    const parsed = JSON.parse(trimmed);
                    return {
                        isJson: true,
                        text: JSON.stringify(parsed, null, 2),
                    };
                } catch (error) {
                    return {
                        isJson: false,
                        text: value,
                    };
                }
            }

            if (typeof value === 'object') {
                try {
                    return {
                        isJson: true,
                        text: JSON.stringify(value, null, 2),
                    };
                } catch (error) {
                    return {
                        isJson: false,
                        text: String(value),
                    };
                }
            }

            return {
                isJson: false,
                text: String(value),
            };
        };

        const METHOD_THEME_MAP = {
            get: 'border-sky-400/60 bg-sky-400/10 text-sky-200',
            post: 'border-emerald-400/60 bg-emerald-400/10 text-emerald-200',
            delete: 'border-rose-400/60 bg-rose-400/10 text-rose-200',
            put: 'border-amber-400/60 bg-amber-400/10 text-amber-200',
            patch: 'border-fuchsia-400/60 bg-fuchsia-400/10 text-fuchsia-200',
            head: 'border-cyan-300/60 bg-cyan-300/10 text-cyan-100',
            options: 'border-indigo-400/60 bg-indigo-400/10 text-indigo-200',
            default: 'border-slate-400/50 bg-slate-400/10 text-slate-200',
        };

        const methodClasses = (method) => {
            if (!method) {
                return METHOD_THEME_MAP.default;
            }

            const normalized = String(method).toLowerCase();
            return METHOD_THEME_MAP[normalized] ?? METHOD_THEME_MAP.default;
        };

        const formattedHeaders = computed(() => formatDisplay(selected.value?.headers ?? null));
        const formattedQuery = computed(() => formatDisplay(selected.value?.query_params ?? null));
        const formattedBody = computed(() => formatDisplay(selected.value?.body ?? null));

        const highlightJsonBlocks = () => {
            if (typeof window === 'undefined' || typeof window.hljs === 'undefined') {
                return;
            }

            nextTick(() => {
                const blocks = document.querySelectorAll('[data-highlight-json]');
                blocks.forEach((block) => {
                    if (block.dataset.highlighted) {
                        block.innerHTML = block.textContent ?? '';
                        block.removeAttribute('data-highlighted');
                        block.classList.remove('hljs');
                    }
                    window.hljs.highlightElement(block);
                });
            });
        };

        watch([formattedHeaders, formattedQuery, formattedBody], () => {
            highlightJsonBlocks();
        }, { immediate: true });

        const canGoPrevious = computed(() => page.value > 1);
        const canGoNext = computed(() => page.value < lastPage.value);

        const changePage = async (nextPage) => {
            const target = Math.min(Math.max(1, nextPage), lastPage.value || 1);
            if (target === page.value) {
                return;
            }

            page.value = target;
            await fetchRequests();
        };

        const goToPreviousPage = () => changePage(page.value - 1);
        const goToNextPage = () => changePage(page.value + 1);

        onMounted(async () => {
            if (typeof window !== 'undefined' && window.hljs) {
                window.hljs.configure({ ignoreUnescapedHTML: true });
            }

            await refresh();
            highlightJsonBlocks();
            intervalId = window.setInterval(fetchRequests, POLLING_INTERVAL_MS);
        });

        onBeforeUnmount(() => {
            if (intervalId) {
                window.clearInterval(intervalId);
            }
        });

        return {
            requests,
            selected,
            loading,
            error,
            page,
            perPage,
            total,
            lastPage,
            canGoPrevious,
            canGoNext,
            goToPreviousPage,
            goToNextPage,
            refresh,
            select,
            deleteOne,
            deleteAll,
            formatDate,
            formattedHeaders,
            formattedQuery,
            formattedBody,
            methodClasses,
            currentYear,
        };
    },
}).mount('#app');
