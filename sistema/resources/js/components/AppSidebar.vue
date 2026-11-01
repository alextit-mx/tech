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
    { title: 'Productos', href: '/admin/products', icon: Boxes },
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
    <Sidebar collapsible="icon" variant="inset"
        ><SidebarHeader
            ><SidebarMenu
                ><SidebarMenuItem
                    ><SidebarMenuButton size="lg" as-child
                        ><Link href="/admin"
                            ><AppLogo /></Link></SidebarMenuButton></SidebarMenuItem></SidebarMenu></SidebarHeader
        ><SidebarContent
            ><SidebarMenu
                ><SidebarMenuItem v-for="item in links" :key="item.href"
                    ><SidebarMenuButton as-child
                        ><Link :href="item.href"
                            ><component :is="item.icon" /><span>{{
                                item.title
                            }}</span></Link
                        ></SidebarMenuButton
                    ></SidebarMenuItem
                ></SidebarMenu
            ></SidebarContent
        ><SidebarFooter><NavUser /></SidebarFooter></Sidebar
    ><slot />
</template>
