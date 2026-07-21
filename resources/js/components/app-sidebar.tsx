import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    Building2,
    ClipboardList,
    ListChecks,
    Globe,
    LayoutGrid,
    Settings,
    ShieldCheck,
    Trophy,
    UsersRound,
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
import { index as auditLogIndex } from '@/routes/audit-log';
import { index as companiesIndex } from '@/routes/companies';
import { index as companyAuditLogIndex } from '@/routes/company-audit-log';
import { index as companyHealthIndex } from '@/routes/company-health';
import { index as directoryIndex } from '@/routes/directory';
import { index as jobsIndex } from '@/routes/jobs';
import {
    index as leadsIndex,
    leaderboard as leaderboardIndex,
} from '@/routes/leads';
import { index as myLeadsIndex } from '@/routes/my-leads';
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

    const canViewLeads =
        (!!auth.company &&
            auth.permissions?.includes('view-company-customers')) ||
        auth.permissions?.includes('view-all-customers');

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(canViewLeads
            ? [
                  {
                      title: 'Leads',
                      href: leadsIndex(),
                      icon: UsersRound,
                  },
              ]
            : []),
        ...(!canViewLeads && auth.permissions?.includes('manage-leads')
            ? [
                  {
                      title: 'My Leads',
                      href: myLeadsIndex(),
                      icon: UsersRound,
                  },
              ]
            : []),
        ...(!!auth.company && auth.permissions?.includes('manage-own-company')
            ? [
                  {
                      title: 'Company Health',
                      href: companyHealthIndex(),
                      icon: Activity,
                  },
              ]
            : []),
        ...(!!auth.company && auth.permissions?.includes('view-reports')
            ? [
                  {
                      title: 'Leaderboard',
                      href: leaderboardIndex(),
                      icon: Trophy,
                  },
              ]
            : []),
        ...(!!auth.company &&
        auth.permissions?.includes('view-reports') &&
        !auth.permissions?.includes('manage-companies')
            ? [
                  {
                      title: 'Activity Log',
                      href: companyAuditLogIndex(),
                      icon: ClipboardList,
                  },
              ]
            : []),
        ...(auth.permissions?.includes('manage-companies')
            ? [
                  {
                      title: 'Directory',
                      href: directoryIndex(),
                      icon: Globe,
                  },
                  {
                      title: 'Jobs',
                      href: jobsIndex(),
                      icon: ListChecks,
                  },
                  {
                      title: 'Activity Log',
                      href: auditLogIndex(),
                      icon: ClipboardList,
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
