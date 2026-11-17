<template>
    <NcContent app-name="hugopreview_nc_app">
        <NcAppContent>
            <main class="hugo-preview-app">
                <router-view />
            </main>
        </NcAppContent>
    </NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'

export default {
    name: 'HugoPreviewApp',
    components: { NcContent, NcAppContent },
}
</script>

<style scoped>
.hugo-preview-app {
    min-height: 100%;
    width: 100%;
}
</style>
