import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type Props = {
    label: string;
    value: number | string;
};

export function StatCard({ label, value }: Props) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-2xl font-semibold tabular-nums">
                    {value}
                </CardTitle>
            </CardHeader>
        </Card>
    );
}
