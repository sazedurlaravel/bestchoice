@php
    $flashMessages = collect([
        'success' => 'success',
        'error' => 'error',
        'warning' => 'warning',
        'info' => 'info',
        'status' => 'info',
    ])->filter(fn (string $type, string $key): bool => session()->has($key))
        ->map(fn (string $type, string $key): array => [
            'type' => $type,
            'message' => (string) session($key),
        ])->values();

    if ($errors->any()) {
        $flashMessages->push([
            'type' => 'error',
            'message' => $errors->all(),
        ]);
    }
@endphp

<script>
    window.bestChoiceFlashMessages = @json($flashMessages);

    document.addEventListener('DOMContentLoaded', async () => {
        const messages = window.bestChoiceFlashMessages || [];
        if (!messages.length || typeof Swal === 'undefined') return;

        for (const flash of messages) {
            const message = Array.isArray(flash.message) ? flash.message.join('\n') : flash.message;
            await Swal.fire({
                icon: flash.type,
                title: flash.type === 'success' ? 'Success' : flash.type === 'error' ? 'Error' : 'Notice',
                text: message,
                toast: true,
                position: 'top-end',
                timer: 3500,
                timerProgressBar: true,
                showConfirmButton: false,
                confirmButtonColor: flash.type === 'success' ? '#164c39' : '#d9903b',
            });
        }

        if (messages.some((flash) => flash.type === 'success')) {
            document.querySelectorAll('.alert-success, .flash.success').forEach((element) => element.remove());
        }
        if (messages.some((flash) => flash.type === 'error')) {
            document.querySelectorAll('.alert-error, .flash.error').forEach((element) => element.remove());
        }
    });
</script>
