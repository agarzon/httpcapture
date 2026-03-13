const { createApp, ref, computed, onMounted, onBeforeUnmount } = Vue;
const POLL_FAST_MS = 1_000;
const POLL_SLOW_MS = 10_000;

createApp({
    setup() {
        const requests = ref([]);
        const selected = ref(null);
        const loading = ref(false);
        const error = ref('');
        const copySuccess = ref(false);
        const confirmingDelete = ref(null);
        const confirmingDeleteAll = ref(false);
        let confirmDeleteTimeout = null;
        let confirmDeleteAllTimeout = null;
        const page = ref(1);
        const perPage = ref(10);
        const total = ref(0);
        const lastPage = ref(1);
        const currentYear = new Date().getFullYear();
        let pollIntervalId = null;
        let knownLatestId = null;
        let knownTotal = 0;

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

            if (confirmingDelete.value !== selected.value.id) {
                confirmingDelete.value = selected.value.id;
                window.clearTimeout(confirmDeleteTimeout);
                confirmDeleteTimeout = window.setTimeout(() => {
                    confirmingDelete.value = null;
                }, 4000);
                return;
            }

            confirmingDelete.value = null;
            window.clearTimeout(confirmDeleteTimeout);

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

            if (!confirmingDeleteAll.value) {
                confirmingDeleteAll.value = true;
                window.clearTimeout(confirmDeleteAllTimeout);
                confirmDeleteAllTimeout = window.setTimeout(() => {
                    confirmingDeleteAll.value = false;
                }, 4000);
                return;
            }

            confirmingDeleteAll.value = false;
            window.clearTimeout(confirmDeleteAllTimeout);

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

        const METHOD_BADGE_MAP = {
            get: 'method-badge--get',
            post: 'method-badge--post',
            delete: 'method-badge--delete',
            put: 'method-badge--put',
            patch: 'method-badge--patch',
            head: 'method-badge--head',
            options: 'method-badge--options',
            default: 'method-badge--default',
        };

        const methodBadgeClass = (method) => {
            if (!method) return METHOD_BADGE_MAP.default;
            const normalized = String(method).toLowerCase();
            return METHOD_BADGE_MAP[normalized] ?? METHOD_BADGE_MAP.default;
        };

        const sections = ref({
            headers: true,
            query: true,
            body: true,
            formData: true,
            files: true,
        });

        const toggleSection = (name) => {
            sections.value[name] = !sections.value[name];
        };

        let copyTimeout = null;
        const copyToClipboard = async (text) => {
            try {
                await navigator.clipboard.writeText(text);
                copySuccess.value = true;
                window.clearTimeout(copyTimeout);
                copyTimeout = window.setTimeout(() => {
                    copySuccess.value = false;
                }, 2000);
            } catch (err) {
                setError('Failed to copy to clipboard');
            }
        };

        const escapeHtml = (unsafe) => {
            if (!unsafe) return '';
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        };

        const linkify = (text) => {
            if (!text) return '';
            const str = String(text);
            const urlRegex = /(https?:\/\/[^\s<>"']+)/g;
            let result = '';
            let lastIndex = 0;
            let match;
            while ((match = urlRegex.exec(str)) !== null) {
                result += escapeHtml(str.slice(lastIndex, match.index));
                const escapedUrl = escapeHtml(match[0]);
                result += `<a href="${escapedUrl}" target="_blank" rel="noopener" class="data-link">${escapedUrl}</a>`;
                lastIndex = match.index + match[0].length;
            }
            result += escapeHtml(str.slice(lastIndex));
            return result;
        };

        const relativeTime = (dateStr) => {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);

            if (diffInSeconds < 60) return 'just now';
            const diffInMinutes = Math.floor(diffInSeconds / 60);
            if (diffInMinutes < 60) return `${diffInMinutes}m ago`;
            const diffInHours = Math.floor(diffInMinutes / 60);
            if (diffInHours < 24) return `${diffInHours}h ago`;
            const diffInDays = Math.floor(diffInHours / 24);
            return `${diffInDays}d ago`;
        };

        const contentTypeBadge = (headers) => {
            if (!headers) return { show: false, label: '' };
            const key = Object.keys(headers).find(k => k.toLowerCase() === 'content-type');
            if (!key) return { show: false, label: '' };

            const value = headers[key].toLowerCase();
            if (value.includes('application/json')) return { show: true, label: 'JSON' };
            if (value.includes('text/html')) return { show: true, label: 'HTML' };
            if (value.includes('multipart/form-data')) return { show: true, label: 'Form' };
            if (value.includes('application/xml') || value.includes('text/xml')) return { show: true, label: 'XML' };
            if (value.includes('text/plain')) return { show: true, label: 'Text' };
            if (value.includes('application/x-www-form-urlencoded')) return { show: true, label: 'UrlEncoded' };

            return { show: false, label: '' };
        };

        const formattedBody = computed(() => {
            const body = selected.value?.body;
            if (body === null || body === undefined || body === '') {
                return { isEmpty: true, lines: [], language: 'Plain Text', raw: '' };
            }

            let content = '';
            let isJson = false;
            let raw = '';

            if (typeof body === 'string') {
                try {
                    const parsed = JSON.parse(body);
                    raw = JSON.stringify(parsed, null, 2);
                    isJson = true;
                } catch (e) {
                    raw = body;
                    isJson = false;
                }
            } else if (typeof body === 'object') {
                raw = JSON.stringify(body, null, 2);
                isJson = true;
            } else {
                raw = String(body);
            }

            if (isJson && window.hljs) {
                content = window.hljs.highlight(raw, { language: 'json' }).value;
                content = content.replace(/<span class="hljs-string">&quot;(.*?)&quot;<\/span>/g, (match, inner) => {
                    const linked = inner.replace(/(https?:\/\/[^\s"<>]+)/g, (url) => {
                        return `<a href="${url}" target="_blank" rel="noopener" class="data-link">${url}</a>`;
                    });
                    return `<span class="hljs-string">&quot;${linked}&quot;</span>`;
                });
            } else {
                content = linkify(raw);
            }

            return {
                isEmpty: false,
                lines: content.split('\n'),
                language: isJson ? 'JSON' : 'Plain Text',
                raw: raw,
            };
        });

        const formattedHeaders = computed(() => selected.value?.headers || {});
        const formattedQuery = computed(() => selected.value?.query_params || {});
        const formattedFormData = computed(() => selected.value?.form_data || {});
        const formattedFiles = computed(() => selected.value?.files || {});

        const hasHeaders = computed(() => Object.keys(formattedHeaders.value).length > 0);
        const hasQuery = computed(() => Object.keys(formattedQuery.value).length > 0);
        const hasFormData = computed(() => Object.keys(formattedFormData.value).length > 0);
        const hasFiles = computed(() => Object.keys(formattedFiles.value).length > 0);

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

        const checkForUpdates = async () => {
            try {
                const payload = await requestJson('/api/requests/poll');
                const newLatestId = payload.latest_id;
                const newTotal = payload.total;

                if (newLatestId !== knownLatestId || newTotal !== knownTotal) {
                    knownLatestId = newLatestId;
                    knownTotal = newTotal;
                    await fetchRequests();
                }
            } catch (err) {
                setError(err.message);
            }
        };

        const startPolling = () => {
            stopPolling();
            const interval = document.hidden ? POLL_SLOW_MS : POLL_FAST_MS;
            pollIntervalId = window.setInterval(checkForUpdates, interval);
        };

        const stopPolling = () => {
            if (pollIntervalId) {
                window.clearInterval(pollIntervalId);
                pollIntervalId = null;
            }
        };

        const onVisibilityChange = () => {
            startPolling();
            if (!document.hidden) {
                checkForUpdates();
            }
        };

        onMounted(async () => {
            if (typeof window !== 'undefined' && window.hljs) {
                window.hljs.configure({ ignoreUnescapedHTML: true });
            }

            await refresh();
            knownLatestId = requests.value.length ? requests.value[0].id : null;
            knownTotal = total.value;
            startPolling();
            document.addEventListener('visibilitychange', onVisibilityChange);
        });

        onBeforeUnmount(() => {
            stopPolling();
            document.removeEventListener('visibilitychange', onVisibilityChange);
        });

        return {
            requests,
            selected,
            loading,
            error,
            copySuccess,
            confirmingDelete,
            confirmingDeleteAll,
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
            formattedFormData,
            formattedFiles,
            hasHeaders,
            hasQuery,
            hasFormData,
            hasFiles,
            methodBadgeClass,
            currentYear,
            sections,
            toggleSection,
            copyToClipboard,
            linkify,
            relativeTime,
            contentTypeBadge,
        };
    },
}).mount('#app');
