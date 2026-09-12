/** Semi-monthly payroll cut-off: the 1st–15th, or the 16th–last day of month. */
export type Cutoff = { from: string; to: string; label: string };

const pad = (n: number) => n.toString().padStart(2, '0');
const iso = (y: number, m: number, d: number) => `${y}-${pad(m)}-${pad(d)}`;
const lastDayOf = (y: number, m: number) => new Date(y, m, 0).getDate(); // m is 1-indexed here

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

function cutoffFor(year: number, month: number, half: 1 | 2): Cutoff {
    const from = half === 1 ? iso(year, month, 1) : iso(year, month, 16);
    const to =
        half === 1
            ? iso(year, month, 15)
            : iso(year, month, lastDayOf(year, month));

    return {
        from,
        to,
        label: `${MONTHS[month - 1]} ${half === 1 ? '1–15' : `16–${lastDayOf(year, month)}`}`,
    };
}

/** The cut-off containing `date` (defaults to today). */
export function currentCutoff(date: Date = new Date()): Cutoff {
    return cutoffFor(
        date.getFullYear(),
        date.getMonth() + 1,
        date.getDate() <= 15 ? 1 : 2,
    );
}

/** The cut-off immediately before the one containing `date`. */
export function previousCutoff(date: Date = new Date()): Cutoff {
    const year = date.getFullYear();
    const month = date.getMonth() + 1;

    if (date.getDate() <= 15) {
        const prevMonth = month === 1 ? 12 : month - 1;
        const prevYear = month === 1 ? year - 1 : year;
        return cutoffFor(prevYear, prevMonth, 2);
    }

    return cutoffFor(year, month, 1);
}
