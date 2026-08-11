/**
 * Pretty-prints a JSON-ish value for display. The value is usually already a
 * decoded object, but child-CRM syncs can hand it over as a raw JSON string
 * instead — stringifying that directly would just quote-wrap it on one line
 * rather than pretty-printing its contents, so parse it first when needed.
 */
export function formatJsonValue(value: unknown): string {
    const parsed = typeof value === 'string' ? tryParseJson(value) : value;

    return JSON.stringify(parsed, null, 4);
}

function tryParseJson(value: string): unknown {
    try {
        return JSON.parse(value);
    } catch {
        return value;
    }
}
