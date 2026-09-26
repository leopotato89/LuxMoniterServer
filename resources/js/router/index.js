import { createRouter, createWebHistory } from 'vue-router';
import AppLayout from '../layouts/AppLayout.vue';
import { useAuthStore } from '../stores/auth';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/Login.vue'),
        meta: { guest: true },
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('../views/Register.vue'),
        meta: { guest: true },
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: () => import('../views/ForgotPassword.vue'),
        meta: { guest: true },
    },
    {
        path: '/reset-password',
        name: 'reset-password',
        component: () => import('../views/ResetPassword.vue'),
        meta: { guest: true },
    },
    {
        path: '/',
        component: AppLayout,
        meta: { auth: true },
        children: [
            {
                path: '',
                name: 'devices.index',
                component: () => import('../views/DevicesIndex.vue'),
            },
            {
                path: ':serial',
                name: 'devices.show',
                component: () => import('../views/DeviceShow.vue'),
            },
            {
                path: 'admin/users',
                name: 'admin.users',
                component: () => import('../views/AdminUsers.vue'),
                meta: { admin: true },
            },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('../views/NotFound.vue'),
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    // Tải lại trang: có token nhưng chưa có user → khôi phục phiên trước khi quyết định.
    if (auth.isAuthenticated && !auth.user) {
        await auth.restore();
    }

    if (to.meta.auth && !auth.isAuthenticated) {
        return { name: 'login' };
    }

    if (to.meta.admin && !auth.isAdmin) {
        return { name: 'devices.index' };
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'devices.index' };
    }
});

export default router;
