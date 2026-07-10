@props(['model', 'id', 'showTitle' => true])
@php
    // [FIX-ATTACHMENT-URL] Resolve URLs server-side so the component works regardless
    // of the app's base path (e.g. /iboa/public/ under Laragon).
    $attUrlIndex   = route('attachments.index');
    $attUrlStore   = route('attachments.store');
    // Template URL for per-attachment endpoints: replace __ID__ on the client side.
    $attUrlShowTpl = route('attachments.download', ['attachment' => '__ID__']);
    $attUrlDelTpl  = route('attachments.destroy',  ['attachment' => '__ID__']);
@endphp

<div x-data="attachmentManager()" class="space-y-4">
    {{-- Header --}}
    @if($showTitle)
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900">Pièces jointes</h3>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700"
              x-text="attachments.length === 0 ? 'Aucun fichier' : attachments.length + ' fichier' + (attachments.length !== 1 ? 's' : '')">Aucun fichier</span>
    </div>
    @endif

    {{-- Upload Form --}}
    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition-colors cursor-pointer"
         @dragover.prevent="isDragging = true"
         @dragleave.prevent="isDragging = false"
         @drop.prevent="handleDrop"
         :class="isDragging && 'border-blue-400 bg-blue-50'">
        <input type="file" multiple hidden @change="handleFileSelect" x-ref="fileInput" />

        <button type="button" @click="$refs.fileInput.click()"
                class="inline-flex flex-col items-center gap-2">
            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
            </svg>
            <div>
                <p class="text-sm font-medium text-gray-700">Cliquez pour ajouter ou glissez-déposez</p>
                <p class="text-xs text-gray-500">Formats : images, PDF, Excel, Word, CSV (max 10 MB)</p>
            </div>
        </button>

        <template x-if="uploading">
            <div class="mt-3">
                <div class="w-full bg-gray-200 rounded-full h-1.5">
                    <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-300" :style="'width: ' + uploadProgress + '%'"></div>
                </div>
                <p class="text-xs text-gray-500 mt-2"><span x-text="uploadProgress"></span>%</p>
            </div>
        </template>
    </div>

    {{-- Error Message --}}
    <template x-if="error">
        <div class="bg-red-50 border border-red-200 rounded-lg p-3 flex items-start gap-2">
            <svg class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <p class="text-sm text-red-700" x-text="error"></p>
        </div>
    </template>

    {{-- Attachments List --}}
    <template x-if="attachments.length > 0">
        <div class="space-y-2">
            <template x-for="(attachment, index) in attachments" :key="index">
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        {{-- File Icon --}}
                        <template x-if="attachment.is_image">
                            <div class="flex-shrink-0">
                                <a :href="urlShowTpl.replace('__ID__', attachment.id)" target="_blank">
                                    <img :src="urlShowTpl.replace('__ID__', attachment.id)" :alt="attachment.filename"
                                         class="w-10 h-10 rounded object-cover" />
                                </a>
                            </div>
                        </template>
                        <template x-if="!attachment.is_image">
                            <div class="flex-shrink-0 w-10 h-10 rounded bg-gray-200 flex items-center justify-center">
                                <template x-if="attachment.is_pdf">
                                    <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"/>
                                    </svg>
                                </template>
                                <template x-if="!attachment.is_pdf">
                                    <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M8 16.5a1 1 0 11-2 0 1 1 0 012 0zM15 7H4v2h11V7zM4 5h16a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1z"/>
                                    </svg>
                                </template>
                            </div>
                        </template>

                        {{-- File Info --}}
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate" :title="attachment.filename" x-text="attachment.filename"></p>
                            <p class="text-xs text-gray-500">
                                <span x-text="attachment.size"></span> •
                                <span x-text="attachment.created_at"></span>
                            </p>
                            <template x-if="attachment.label">
                                <p class="text-xs text-gray-600 mt-1 italic">"<span x-text="attachment.label"></span>"</p>
                            </template>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                        <a :href="urlShowTpl.replace('__ID__', attachment.id)"
                           class="p-1 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                           title="Télécharger">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                        </a>
                        <button type="button" @click="deleteAttachment(attachment.id, index)"
                                class="p-1 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
                                title="Supprimer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </template>

    {{-- Empty State --}}
    <template x-if="attachments.length === 0 && !uploading">
        <div class="text-center py-6 text-gray-500">
            <svg class="w-12 h-12 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-sm">Aucune pièce jointe</p>
        </div>
    </template>
</div>

<script>
function attachmentManager() {
    return {
        attachments: [],
        uploading: false,
        uploadProgress: 0,
        isDragging: false,
        error: null,
        modelType: '{{ $model }}',
        modelId: {{ $id }},
        urlIndex:   @json($attUrlIndex),
        urlStore:   @json($attUrlStore),
        urlShowTpl: @json($attUrlShowTpl),
        urlDelTpl:  @json($attUrlDelTpl),

        async init() {
            await this.loadAttachments();
        },

        async loadAttachments() {
            try {
                const url = `${this.urlIndex}?attachable_type=${encodeURIComponent(this.modelType)}&attachable_id=${encodeURIComponent(this.modelId)}`;
                const response = await fetch(url);
                if (!response.ok) throw new Error('Erreur lors du chargement');
                this.attachments = await response.json();
            } catch (err) {
                console.error('Erreur:', err);
                this.error = 'Impossible de charger les pièces jointes';
            }
        },

        handleFileSelect(e) {
            const files = Array.from(e.target.files);
            this.uploadFiles(files);
            e.target.value = ''; // Reset input
        },

        handleDrop(e) {
            this.isDragging = false;
            const files = Array.from(e.dataTransfer.files);
            this.uploadFiles(files);
        },

        async uploadFiles(files) {
            if (!files.length) return;

            this.error = null;
            const allowedMimes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain', 'text/csv'
            ];

            for (const file of files) {
                if (!allowedMimes.includes(file.type)) {
                    this.error = `${file.name} : Type de fichier non autorisé`;
                    continue;
                }
                if (file.size > 10 * 1024 * 1024) {
                    this.error = `${file.name} : Fichier trop volumineux (max 10 MB)`;
                    continue;
                }

                await this.uploadFile(file);
            }

            this.uploading = false;
            this.uploadProgress = 0;
        },

        async uploadFile(file) {
            this.uploading = true;
            const formData = new FormData();
            formData.append('file', file);
            formData.append('attachable_type', this.modelType);
            formData.append('attachable_id', this.modelId);

            try {
                const response = await fetch(this.urlStore, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: formData
                });

                if (!response.ok) {
                    const data = await response.json();
                    throw new Error(data.error || 'Erreur lors de l\'upload');
                }

                const newAttachment = await response.json();
                this.attachments.push(newAttachment);
            } catch (err) {
                this.error = err.message;
                console.error('Erreur upload:', err);
            }
        },

        async deleteAttachment(id, index) {
            const ok = await window.erpConfirm({
                message: 'Supprimer cette pièce jointe ?',
                confirmLabel: 'Supprimer',
                isDanger: true,
            });
            if (!ok) return;

            try {
                const response = await fetch(this.urlDelTpl.replace('__ID__', id), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    }
                });

                if (!response.ok) throw new Error('Erreur lors de la suppression');
                this.attachments.splice(index, 1);
                window.toast('Pièce jointe supprimée.', 'success');
            } catch (err) {
                window.toast('Impossible de supprimer la pièce jointe.', 'error');
                console.error('Erreur suppression:', err);
            }
        }
    };
}
</script>
