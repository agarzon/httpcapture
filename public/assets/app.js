const { createApp, ref, onMounted, onBeforeUnmount } = Vue;

createApp({
    setup() {
        const requests = ref([]);
        const selected = ref(null);
        const loading = ref(false);
        const error = ref('');
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
                } catch (e) {
                    detail = response.statusText;
                }

                throw new Error(detail || 'Request failed');
            }

            return response.json();
        };

        const fetchRequests = async () => {
            try {
                const payload = await requestJson('/api/requests');
                requests.value = payload.data || [];

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
            } catch (e) {
                return value;
            }
        };

        const pretty = (value) => {
            if (!value || Object.keys(value).length === 0) {
                return '(empty)';
            }

            try {
                return JSON.stringify(value, null, 2);
            } catch (e) {
                return String(value);
            }
        };

        onMounted(async () => {
            await refresh();
            intervalId = window.setInterval(fetchRequests, 10_000);
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
            refresh,
            select,
            deleteOne,
            deleteAll,
            formatDate,
            pretty,
        };
    },
}).mount('#app');
