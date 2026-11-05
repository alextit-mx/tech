<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    Boxes,
    Tags,
    Factory,
    Users,
    Settings,
    FileText,
    Contact,
    Wrench,
    ShieldCheck,
    CalendarCheck,
    GalleryHorizontal,
    Cpu,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
const page = usePage<any>();
const links = [
    { title: 'Inicio', href: '/admin', icon: LayoutDashboard },
    { title: 'Cotizaciones', href: '/admin/quotes', icon: FileText },
    { title: 'Clientes', href: '/admin/customers', icon: Contact },
    { title: 'Servicio técnico', href: '/admin/service', icon: Wrench },
    { title: 'Garantías', href: '/admin/warranties', icon: ShieldCheck },
    { title: 'Pólizas', href: '/admin/plans', icon: CalendarCheck },
    { title: 'Productos', href: '/admin/products', icon: Boxes },
    ...(page.props.pendingSpecsCount !== null
        ? [
              {
                  title: 'Fichas pendientes',
                  href: '/admin/fichas',
                  icon: FileText,
              },
          ]
        : []),
    { title: 'Equipos armados', href: '/admin/builds', icon: Cpu },
    { title: 'Banners', href: '/admin/banners', icon: GalleryHorizontal },
    { title: 'Marcas', href: '/admin/brands', icon: Factory },
    ...(page.props.auth?.user?.role === 'owner'
        ? [
              { title: 'Categorías', href: '/admin/categories', icon: Tags },
              { title: 'Usuarios', href: '/admin/users', icon: Users },
              {
                  title: 'Configuración',
                  href: '/admin/settings',
                  icon: Settings,
              },
          ]
        : []),
];
</script>
<template>
    <Sidebar collapsible="icon" variant="sidebar"
        ><SidebarHeader
            ><SidebarMenu
                ><SidebarMenuItem
                    ><SidebarMenuButton size="lg" as-child
                        ><Link href="/admin"
                            ><AppLogo /></Link></SidebarMenuButton></SidebarMenuItem></SidebarMenu></SidebarHeader
        ><SidebarContent
            ><SidebarMenu
                ><SidebarMenuItem v-for="item in links" :key="item.href"
                    ><SidebarMenuButton
                        as-child
                        :tooltip="item.title"
                        :is-active="
                            item.href === '/admin'
                                ? page.url.split('?')[0] === item.href
                                : page.url.split('?')[0].startsWith(item.href)
                        "
                        ><Link
                            :href="item.href"
                            :aria-current="
                                (
                                    item.href === '/admin'
                                        ? page.url.split('?')[0] === item.href
                                        : page.url
                                              .split('?')[0]
                                              .startsWith(item.href)
                                )
                                    ? 'page'
                                    : undefined
                            "
                            ><component :is="item.icon" /><span>{{
                                item.title
                            }}</span
                            ><span
                                v-if="item.href === '/admin/fichas'"
                                class="ml-auto text-xs tabular-nums"
                                >{{ page.props.pendingSpecsCount }}</span
                            ></Link
                        ></SidebarMenuButton
                    ></SidebarMenuItem
                ></SidebarMenu
            ></SidebarContent
        ><SidebarFooter><NavUser /></SidebarFooter></Sidebar
    ><slot />
</template>
