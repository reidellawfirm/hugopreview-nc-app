import { createApp } from 'vue'
import { createRouter, createWebHashHistory } from 'vue-router'
import { imagePath } from '@nextcloud/router'

import App from './App.vue'
import Projects from './views/HugoProjects.vue'
import ProjectDetail from './views/HugoProjectDetail.vue'
import Preview from './views/HugoPreview.vue'

const router = createRouter({
    history: createWebHashHistory(),
    routes: [
        { path: '/', redirect: '/projects' },
        { path: '/projects', name: 'projects', component: Projects },
        { path: '/projects/:id', name: 'project', component: ProjectDetail, props: true },
        { path: '/preview/:id', name: 'preview', component: Preview, props: true },
        { path: '/:pathMatch(.*)*', redirect: '/projects' },
    ],
})

router.afterEach(() => {
    document.title = 'Hugo Preview - Nextcloud'
    let link = document.querySelector("link[rel~='icon']")
    if (!link) {
        link = document.createElement('link')
        link.rel = 'icon'
        document.head.appendChild(link)
    }
    link.href = imagePath('hugopreview_nc_app', 'favicon.svg')
})

createApp(App).use(router).mount('#hugo-preview-app')
