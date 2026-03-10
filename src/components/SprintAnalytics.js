import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const SprintAnalytics = ({ sprintId }) => {
    const [data, setData] = useState(null);
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        if (!sprintId) return;
        setIsLoading(true);
        apiFetch({ path: `/es-scrum/v1/sprints/${sprintId}/analytics` })
            .then((res) => {
                setData(res);
                setIsLoading(false);
            })
            .catch((err) => {
                console.error('Analytics fetch failed:', err);
                setIsLoading(false);
            });
    }, [sprintId]);

    if (isLoading) return <div style={{ textAlign: 'center', padding: '12px' }}><Spinner /></div>;
    if (!data) return <div style={styles.empty}>No analytics data available.</div>;

    const total = data.total_tasks || 0;

    return (
        <div style={styles.container}>
            <h4 style={styles.title}>{__('Sprint Analytics', 'es-scrum')}</h4>

            {/* Progress bar */}
            {total > 0 && (
                <div style={styles.progressContainer}>
                    <div style={styles.progressBar}>
                        {data.completed > 0 && (
                            <div style={{ ...styles.segment, width: `${(data.completed / total) * 100}%`, background: '#00a32a' }}
                                title={`Done: ${data.completed}`}
                            />
                        )}
                        {data.in_progress > 0 && (
                            <div style={{ ...styles.segment, width: `${(data.in_progress / total) * 100}%`, background: '#2271b1' }}
                                title={`In Progress: ${data.in_progress}`}
                            />
                        )}
                        {data.todo > 0 && (
                            <div style={{ ...styles.segment, width: `${(data.todo / total) * 100}%`, background: '#dba617' }}
                                title={`Todo: ${data.todo}`}
                            />
                        )}
                        {data.backlog > 0 && (
                            <div style={{ ...styles.segment, width: `${(data.backlog / total) * 100}%`, background: '#ccc' }}
                                title={`Backlog: ${data.backlog}`}
                            />
                        )}
                    </div>
                    <div style={styles.legend}>
                        <span><span style={{ ...styles.dot, background: '#00a32a' }} /> Done {data.completed}</span>
                        <span><span style={{ ...styles.dot, background: '#2271b1' }} /> In Progress {data.in_progress}</span>
                        <span><span style={{ ...styles.dot, background: '#dba617' }} /> Todo {data.todo}</span>
                        <span><span style={{ ...styles.dot, background: '#ccc' }} /> Backlog {data.backlog}</span>
                    </div>
                </div>
            )}

            {/* Stats grid */}
            <div style={styles.grid}>
                <div style={styles.stat}>
                    <div style={styles.statValue}>{data.completion_rate}%</div>
                    <div style={styles.statLabel}>Completion</div>
                </div>
                <div style={styles.stat}>
                    <div style={styles.statValue}>{data.total_tasks}</div>
                    <div style={styles.statLabel}>Total Tasks</div>
                </div>
                <div style={styles.stat}>
                    <div style={styles.statValue}>{data.completed_story_points}/{data.total_story_points}</div>
                    <div style={styles.statLabel}>Story Points</div>
                </div>
                <div style={styles.stat}>
                    <div style={styles.statValue}>{data.velocity}</div>
                    <div style={styles.statLabel}>Velocity</div>
                </div>
            </div>
        </div>
    );
};

const styles = {
    container: {
        background: '#f0f6fc',
        border: '1px solid #c3d1e0',
        borderRadius: '6px',
        padding: '16px',
    },
    title: {
        margin: '0 0 12px 0',
        fontSize: '14px',
        color: '#1d2327',
    },
    empty: {
        textAlign: 'center',
        color: '#757575',
        padding: '12px',
        fontSize: '13px',
    },
    progressContainer: {
        marginBottom: '16px',
    },
    progressBar: {
        display: 'flex',
        height: '12px',
        borderRadius: '6px',
        overflow: 'hidden',
        background: '#e0e0e0',
    },
    segment: {
        height: '100%',
        transition: 'width 0.3s ease',
    },
    legend: {
        display: 'flex',
        gap: '12px',
        marginTop: '6px',
        fontSize: '11px',
        color: '#555',
        flexWrap: 'wrap',
    },
    dot: {
        display: 'inline-block',
        width: '8px',
        height: '8px',
        borderRadius: '50%',
        marginRight: '4px',
    },
    grid: {
        display: 'grid',
        gridTemplateColumns: '1fr 1fr',
        gap: '8px',
    },
    stat: {
        textAlign: 'center',
        padding: '10px',
        background: '#fff',
        borderRadius: '4px',
        border: '1px solid #e0e0e0',
    },
    statValue: {
        fontSize: '20px',
        fontWeight: 700,
        color: '#1d2327',
    },
    statLabel: {
        fontSize: '11px',
        color: '#757575',
        textTransform: 'uppercase',
        marginTop: '2px',
    },
};

export default SprintAnalytics;
