import { Head } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { dashboard } from '@/routes';

export default function CompanyInactive() {
    return (
        <>
            <Head title="Company inactive" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Company inactive"
                    description="Your company's account has been deactivated"
                />

                <Alert>
                    <ShieldAlert />
                    <AlertTitle>No data can be shown</AlertTitle>
                    <AlertDescription>
                        This company&apos;s data is inactive. No data can be
                        shown until it is reactivated. Contact your
                        administrator for assistance.
                    </AlertDescription>
                </Alert>
            </div>
        </>
    );
}

CompanyInactive.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
