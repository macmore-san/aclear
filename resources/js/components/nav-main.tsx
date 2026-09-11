import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { resolveMenuIcon } from '@/lib/icons';
import type { MenuItem } from '@/types';

export function NavMain({ items }: { items: MenuItem[] }) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Platform</SidebarGroupLabel>
            {/* Ledger rule: every top-level row is divided, not just spaced. */}
            <SidebarMenu className="gap-0">
                {items.map((item) =>
                    item.children.length > 0 ? (
                        <Collapsible
                            key={item.id}
                            asChild
                            defaultOpen={item.children.some((child) =>
                                child.href
                                    ? isCurrentOrParentUrl(child.href)
                                    : false,
                            )}
                            className="group/collapsible"
                        >
                            <SidebarMenuItem className="border-sidebar-border/70 border-b last:border-b-0">
                                <CollapsibleTrigger asChild>
                                    <SidebarMenuButton
                                        tooltip={{ children: item.title }}
                                    >
                                        {(() => {
                                            const Icon = resolveMenuIcon(
                                                item.icon,
                                            );
                                            return Icon && <Icon />;
                                        })()}
                                        <span>{item.title}</span>
                                        <ChevronRight className="ml-auto transition-transform duration-200 ease-out group-data-[state=open]/collapsible:rotate-90" />
                                    </SidebarMenuButton>
                                </CollapsibleTrigger>
                                <CollapsibleContent className="data-[state=closed]:animate-collapsible-up data-[state=open]:animate-collapsible-down overflow-hidden">
                                    <SidebarMenuSub>
                                        {item.children.map((child) => (
                                            <SidebarMenuSubItem key={child.id}>
                                                <SidebarMenuSubButton
                                                    asChild
                                                    isActive={
                                                        child.href
                                                            ? isCurrentUrl(
                                                                  child.href,
                                                              )
                                                            : false
                                                    }
                                                >
                                                    <Link
                                                        href={child.href ?? '#'}
                                                        prefetch
                                                    >
                                                        <span>
                                                            {child.title}
                                                        </span>
                                                    </Link>
                                                </SidebarMenuSubButton>
                                            </SidebarMenuSubItem>
                                        ))}
                                    </SidebarMenuSub>
                                </CollapsibleContent>
                            </SidebarMenuItem>
                        </Collapsible>
                    ) : (
                        <SidebarMenuItem
                            key={item.id}
                            className="border-sidebar-border/70 border-b last:border-b-0"
                        >
                            <SidebarMenuButton
                                asChild
                                isActive={
                                    item.href ? isCurrentUrl(item.href) : false
                                }
                                tooltip={{ children: item.title }}
                            >
                                <Link href={item.href ?? '#'} prefetch>
                                    {(() => {
                                        const Icon = resolveMenuIcon(item.icon);
                                        return Icon && <Icon />;
                                    })()}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}
