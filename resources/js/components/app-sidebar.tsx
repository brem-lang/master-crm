import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    Globe,
    LayoutGrid,
    Settings,
    ShieldCheck,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Badge } from '@/components/ui/badge';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as companiesIndex } from '@/routes/companies';
import { index as directoryIndex } from '@/routes/directory';
import { index as rolesIndex } from '@/routes/roles';
import { index as usersIndex } from '@/routes/users';
import type { Auth, NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;

    const settingsItems: NavItem[] = [
        ...(auth.permissions?.includes('manage-companies')
            ? [
                  {
                      title: 'Companies',
                      href: companiesIndex(),
                      icon: Building2,
                  },
              ]
            : []),
        ...(auth.permissions?.includes('manage-users')
            ? [
                  {
                      title: 'Users',
                      href: usersIndex(),
                      icon: Users,
                  },
              ]
            : []),
        ...(auth.permissions?.includes('manage-roles')
            ? [
                  {
                      title: 'Roles & Permissions',
                      href: rolesIndex(),
                      icon: ShieldCheck,
                  },
              ]
            : []),
    ];

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(auth.permissions?.includes('manage-companies')
            ? [
                  {
                      title: 'Directory',
                      href: directoryIndex(),
                      icon: Globe,
                  },
              ]
            : []),
        ...(settingsItems.length > 0
            ? [
                  {
                      title: 'Settings',
                      href: usersIndex(),
                      icon: Settings,
                      items: settingsItems,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    {auth.company && (
                        <SidebarMenuItem className="px-2 group-data-[collapsible=icon]:hidden">
                            <Badge
                                variant="secondary"
                                className="w-full justify-center"
                            >
                                {auth.company.name}
                            </Badge>
                        </SidebarMenuItem>
                    )}
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
