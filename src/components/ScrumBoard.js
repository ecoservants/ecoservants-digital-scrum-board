import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Spinner, Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import SprintFilter from './SprintFilter';
import SprintManager from './SprintManager';

const COLUMNS = {
    backlog: { label: 'Backlog', color: '#ddd' },
    todo: { label: 'To Do', color: '#dba617' },
    'in-progress': { label: 'In Progress', color: '#2271b1' },
    done: { label: 'Done', color: '#00a32a' },
};

const ScrumBoard = () => {
    const [tasks, setTasks] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);
    const [selectedSprintId, setSelectedSprintId] = useState(null);
    const [sprintManagerOpen, setSprintManagerOpen] = useState(false);
    const [refreshKey, setRefreshKey] = useState(0);

    const fetchTasks = useCallback(() => {
        setIsLoading(true);
        let path = '/es-scrum/v1/tasks?per_page=100';
        if (selectedSprintId) {
            path += `&sprint_id=${selectedSprintId}`;
        }
        apiFetch({ path })
            .then((data) => {
                setTasks(data);
                setIsLoading(false);
            })
            .catch((err) => {
                console.error(err);
                setError(err.message);
                setIsLoading(false);
            });
    }, [selectedSprintId]);

    useEffect(() => {
        fetchTasks();
    }, [fetchTasks]);

    const handleSprintChange = (sprintId) => {
        setSelectedSprintId(sprintId);
    };

    const handleSprintDataChange = () => {
        // Force SprintFilter to refresh its sprint list
        setRefreshKey((k) => k + 1);
        // Re-fetch tasks in case sprint assignments changed
        fetchTasks();
    };

    if (error) {
        return <div className="notice notice-error"><p>{error}</p></div>;
    }

    // Organize tasks into columns
    const columns = {};
    Object.keys(COLUMNS).forEach((key) => { columns[key] = []; });
    tasks.forEach((task) => {
        const status = task.status || 'backlog';
        if (columns[status]) {
            columns[status].push(task);
        } else {
            columns.backlog.push(task);
        }
    });

    return (
        <div className="es-scrum-board">
            {/* Toolbar */}
            <div style={styles.toolbar}>
                <SprintFilter
                    key={refreshKey}
                    selectedSprintId={selectedSprintId}
                    onSprintChange={handleSprintChange}
                />
                <Button
                    variant="secondary"
                    onClick={() => setSprintManagerOpen(true)}
                    icon="calendar-alt"
                >
                    {__('Manage Sprints', 'es-scrum')}
                </Button>
            </div>

            {/* Board columns */}
            {isLoading ? (
                <div style={{ textAlign: 'center', padding: '40px' }}><Spinner /></div>
            ) : (
                <div style={styles.board}>
                    {Object.entries(COLUMNS).map(([status, meta]) => (
                        <div key={status} style={styles.column}>
                            <div style={{ ...styles.columnHeader, borderBottom: `3px solid ${meta.color}` }}>
                                <span>{meta.label}</span>
                                <span style={styles.count}>{columns[status].length}</span>
                            </div>
                            <div style={styles.columnBody}>
                                {columns[status].length === 0 && (
                                    <div style={styles.emptyCol}>No tasks</div>
                                )}
                                {columns[status].map((task) => (
                                    <TaskCard key={task.id} task={task} />
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Sprint Manager Panel */}
            <SprintManager
                isOpen={sprintManagerOpen}
                onClose={() => setSprintManagerOpen(false)}
                onSprintChange={handleSprintDataChange}
            />
        </div>
    );
};

const TaskCard = ({ task }) => {
    const priorityColors = {
        high: '#d63638',
        medium: '#dba617',
        low: '#00a32a',
    };

    return (
        <Card size="small" style={styles.card}>
            <CardHeader style={{ padding: '8px 12px' }}>
                <div style={{ flex: 1 }}>
                    <strong style={{ fontSize: '13px' }}>{task.title}</strong>
                    {task.sprint_id && (
                        <span style={styles.sprintBadge}>Sprint</span>
                    )}
                </div>
                {task.priority && (
                    <span style={{
                        ...styles.priorityDot,
                        background: priorityColors[task.priority] || '#ccc',
                    }}
                        title={task.priority}
                    />
                )}
            </CardHeader>
            {task.description && (
                <CardBody style={{ padding: '6px 12px 10px' }}>
                    <div style={styles.description}>{task.description}</div>
                </CardBody>
            )}
            {(task.story_points || task.assignee_id) && (
                <div style={styles.cardFooter}>
                    {task.story_points && (
                        <span style={styles.points}>{task.story_points} pts</span>
                    )}
                    {task.assignee_id && (
                        <span style={styles.assignee}>👤</span>
                    )}
                </div>
            )}
        </Card>
    );
};

const styles = {
    toolbar: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'flex-end',
        marginBottom: '16px',
        gap: '12px',
        flexWrap: 'wrap',
    },
    board: {
        display: 'flex',
        gap: '12px',
        alignItems: 'flex-start',
    },
    column: {
        flex: 1,
        minWidth: '200px',
        background: '#f0f0f1',
        borderRadius: '6px',
        overflow: 'hidden',
    },
    columnHeader: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        padding: '10px 12px',
        fontWeight: 600,
        fontSize: '14px',
        textTransform: 'uppercase',
        letterSpacing: '0.5px',
        background: '#e8e8e8',
    },
    count: {
        background: '#fff',
        padding: '2px 8px',
        borderRadius: '10px',
        fontSize: '12px',
        fontWeight: 700,
    },
    columnBody: {
        padding: '8px',
        minHeight: '100px',
    },
    emptyCol: {
        textAlign: 'center',
        color: '#999',
        padding: '20px 0',
        fontSize: '13px',
    },
    card: {
        marginBottom: '8px',
        border: '1px solid #ddd',
        background: '#fff',
    },
    description: {
        fontSize: '12px',
        color: '#555',
        lineHeight: 1.4,
        maxHeight: '40px',
        overflow: 'hidden',
    },
    sprintBadge: {
        display: 'inline-block',
        marginLeft: '6px',
        fontSize: '10px',
        padding: '1px 6px',
        background: '#2271b1',
        color: '#fff',
        borderRadius: '8px',
        verticalAlign: 'middle',
    },
    priorityDot: {
        display: 'inline-block',
        width: '10px',
        height: '10px',
        borderRadius: '50%',
        flexShrink: 0,
    },
    cardFooter: {
        display: 'flex',
        justifyContent: 'space-between',
        padding: '4px 12px 8px',
        fontSize: '12px',
        color: '#757575',
    },
    points: {
        background: '#f0f0f1',
        padding: '1px 6px',
        borderRadius: '4px',
        fontWeight: 600,
        fontSize: '11px',
    },
    assignee: {
        fontSize: '14px',
    },
};

export default ScrumBoard;
