export default function Skeleton({ rows = 3 }: { rows?: number }) {
    return (
        <div className="space-y-3">
            {Array.from({ length: rows }, (_, index) => (
                <div
                    key={index}
                    className="shimmer h-3.5 rounded"
                    style={{ width: `${100 - index * 12}%` }}
                />
            ))}
        </div>
    );
}
