import { Form, Head, usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
import CompanyHealthController from '@/actions/App/Http/Controllers/CompanyHealthController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { WebsiteStatusBadge } from '@/components/website-status-badge';
import { relativeTime } from '@/lib/relative-time';
import { index as companyHealthIndex } from '@/routes/company-health';
import type { Company } from '@/types';

function syncBadgeClass(failureStreak: number): string {
    return failureStreak > 0
        ? 'border-transparent bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
        : 'border-transparent bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
}

type PageProps = {
    company: Company;
};

export default function CompanyHealthPage() {
    const { company } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Company Health" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Company Health"
                    description="Sync status and reachability for your company"
                />

                <Card>
                    <CardHeader className="flex-row items-center justify-between gap-2">
                        <CardTitle>{company.name}</CardTitle>
                        <Badge
                            variant="outline"
                            className={
                                company.is_active
                                    ? 'border-transparent bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                    : 'border-transparent bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                            }
                        >
                            {company.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Website
                                </p>
                                {company.website ? (
                                    <WebsiteStatusBadge
                                        status={company.website_status}
                                    />
                                ) : (
                                    <span className="text-sm text-muted-foreground">
                                        No website configured
                                    </span>
                                )}
                            </div>

                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Sync
                                </p>
                                <div className="flex flex-col gap-1">
                                    <Badge
                                        variant="outline"
                                        className={syncBadgeClass(
                                            company.failure_streak ?? 0,
                                        )}
                                    >
                                        {(company.failure_streak ?? 0) > 0
                                            ? `${company.failure_streak} failed in a row`
                                            : 'Healthy'}
                                    </Badge>
                                    <span className="text-xs text-muted-foreground">
                                        {company.last_synced_at
                                            ? relativeTime(
                                                  company.last_synced_at,
                                              )
                                            : 'Never synced'}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {company.is_active && (
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button variant="outline">
                                        <Download />
                                        Pull data
                                    </Button>
                                </DialogTrigger>

                                <DialogContent>
                                    <DialogTitle>
                                        Pull data from {company.name}?
                                    </DialogTitle>
                                    <DialogDescription>
                                        This will contact {company.name}
                                        &apos;s API to check for new data. It
                                        won&apos;t change anything in this CRM.
                                    </DialogDescription>

                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button variant="secondary">
                                                Cancel
                                            </Button>
                                        </DialogClose>

                                        <Form
                                            {...CompanyHealthController.pullData.form()}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    disabled={processing}
                                                    type="submit"
                                                >
                                                    <Download />
                                                    Pull data
                                                </Button>
                                            )}
                                        </Form>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CompanyHealthPage.layout = {
    breadcrumbs: [
        {
            title: 'Company Health',
            href: companyHealthIndex(),
        },
    ],
};
