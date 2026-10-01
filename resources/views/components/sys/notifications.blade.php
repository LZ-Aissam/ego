@props(['items' => []])
<div x-data="sysNotifications">
    <script type="application/json" id="sys-notifications-data">{!! json_encode($items, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_THROW_ON_ERROR) !!}</script>
    <template x-if="current">
        <div class="sys-overlay" role="dialog" aria-modal="true" :aria-label="current.title" @click="dismiss()">
            <div class="sys-window sys-pop" :class="current.variant === 'alert' && 'sys-window--alert'">
                <header class="sys-window-title"><span class="sys-window-icon" aria-hidden="true">!</span><span x-text="current.title"></span></header>
                <template x-if="current.highlight">
                    <div class="mb-3">
                        <span class="sys-burst" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
                        <span class="sys-badge" x-text="current.highlight"></span>
                    </div>
                </template>
                <template x-for="(line, i) in current.lines" :key="i">
                    <p :class="i === 0 ? 'text-lg font-bold text-frost' : 'mt-1 text-mist sys-num'" x-text="line"></p>
                </template>
                <p class="sys-label mt-5">Touche pour continuer</p>
            </div>
        </div>
    </template>
</div>
