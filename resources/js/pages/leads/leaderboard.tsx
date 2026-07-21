import { Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { RefreshButton } from '@/components/refresh-button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { leaderboard as leaderboardIndex } from '@/routes/leads';

type LeaderboardEntry = {
    id: number;
    name: string;
    total: number;
    ftd: number;
    conversion_rate: number;
};

type PageProps = {
    leaderboard: LeaderboardEntry[];
};

export default function SalesRepLeaderboard() {
    const { leaderboard } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Leaderboard" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Leaderboard"
                        description="Sales rep performance for your company"
                    />
                    <RefreshButton />
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Rep</TableHead>
                                <TableHead className="text-right">
                                    Assigned
                                </TableHead>
                                <TableHead className="text-right">
                                    FTD
                                </TableHead>
                                <TableHead className="text-right">
                                    Conversion rate
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {leaderboard.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="py-8 text-center text-sm text-muted-foreground"
                                    >
                                        No sales reps in your company yet.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                leaderboard.map((rep) => (
                                    <TableRow key={rep.id}>
                                        <TableCell className="font-medium">
                                            {rep.name}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {rep.total}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {rep.ftd}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {rep.conversion_rate.toFixed(1)}%
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

SalesRepLeaderboard.layout = {
    breadcrumbs: [
        {
            title: 'Leaderboard',
            href: leaderboardIndex(),
        },
    ],
};
