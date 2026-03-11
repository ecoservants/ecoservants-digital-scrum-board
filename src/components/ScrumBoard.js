import { useState, useEffect, useCallback, memo } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Spinner, Button, Card, CardBody, CardHeader, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import SprintFilter from './SprintFilter';
import SprintManager from './SprintManager';
import CommentThread from './CommentThread';
import BoardConfigModal from './BoardConfigModal';
import UserProfileModal from './UserProfileModal';
import { defaultConfig } from '../utils/defaultConfig';

const COLUMNS = {
    backlog: { label: 'Backlog', color: '#ddd' },
    todo: { label: 'To Do', color: '#dba617' },
    'in-progress': { label: 'In Progress', color: '#2271b1' },
    done: { label: 'Done', color: '#00a32a' },
};

const PRIORITY_COLORS = {
    high: '#d63638',
    medium: '#dba617',
    low: '#00a32a',
};

/**
 * Format a date string for display.
 * Pure function — no closure deps, safe at module level.
 */
const formatDate = (dateStr) => {
    if (!dateStr) return null;
    return new Date(dateStr).toLocaleDateString(undefined, {
        year: 'numeric', month: 'short', day: 'numeric',
    });
};

const ScrumBoard = () => {
    const [tasks, setTasks] = useState([]);
    const [config, setConfig] = useState(defaultConfig);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);
    const [selectedSprintId, setSelectedSprintId] = useState(null);
    const [sprintManagerOpen, setSprintManagerOpen] = useState(false);
    const [refreshKey, setRefreshKey] = useState(0);

    // Task detail modal — store ID, derive task from live array
    const [selectedTaskId, setSelectedTaskId] = useState(null);

    // Board config modal
    const [isConfigOpen, setIsConfigOpen] = useState(false);

    // User profile modal
    const [isProfileOpen, setIsProfileOpen] = useState(false);
    const [profileUserId, setProfileUserId] = useState(null);
    const [currentUserId, setCurrentUserId] = useState(null);

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
        // Fetch tasks + config + current user in parallel
        Promise.all([
            apiFetch({ path: '/es-scrum/v1/config' }).catch(() => null),
            apiFetch({ path: '/wp/v2/users/me' }).catch(() => null),
        ]).then(([configData, userData]) => {
            if (configData) setConfig(configData);
            if (userData) setCurrentUserId(userData.id);
        });
    }, []);

    useEffect(() => {
        fetchTasks();
    }, [fetchTasks]);

    const handleSprintChange = (sprintId) => {
        setSelectedSprintId(sprintId);
    };

    const handleSprintDataChange = () => {
        setRefreshKey((k) => k + 1);
        fetchTasks();
    };

    const openModal = useCallback((task) => {
        setSelectedTaskId(task.id);
    }, []);

    const closeModal = useCallback(() => {
        setSelectedTaskId(null);
    }, []);

    const handleProfileClick = useCallback((userId) => {
        setProfileUserId(userId);
        setIsProfileOpen(true);
    }, []);

    const openMyProfile = () => {
        if (currentUserId) {
            setProfileUserId(currentUserId);
            setIsProfileOpen(true);
        }
    };

    const saveConfig = (newConfig) => {
        setIsLoading(true);
        apiFetch({
            path: '/es-scrum/v1/config',
            method: 'POST',
            data: newConfig,
        })
            .then(() => {
                setConfig(newConfig);
                setIsLoading(false);
            })
            .catch((err) => {
                console.error(err);
                setError(__('Failed to save configuration.', 'es-scrum'));
                setIsLoading(false);
            });
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

    // Derive selected task from live array (avoids stale snapshot)
    const selectedTask = selectedTaskId
        ? tasks.find((t) => t.id === selectedTaskId) || null
        : null;

    return (
        <div className="es-scrum-board">
            {/* Toolbar */}
            <div style={styles.toolbar}>
                <SprintFilter
                    key={refreshKey}
                    selectedSprintId={selectedSprintId}
                    onSprintChange={handleSprintChange}
                />
                <div style={{ display: 'flex', gap: '8px' }}>
                    <Button
                        variant="secondary"
                        onClick={openMyProfile}
                    >
                        {__('My Profile', 'es-scrum')}
                    </Button>
                    <Button
                        variant="secondary"
                        onClick={() => setIsConfigOpen(true)}
                    >
                        {__('Customize Board', 'es-scrum')}
                    </Button>
                    <Button
                        variant="secondary"
                        onClick={() => setSprintManagerOpen(true)}
                        icon="calendar-alt"
                    >
                        {__('Manage Sprints', 'es-scrum')}
                    </Button>
                </div>
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
                                    <TaskCard
                                        key={task.id}
                                        task={task}
                                        onViewDetails={openModal}
                                        onProfileClick={handleProfileClick}
                                    />
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

            {/* Task Detail Modal */}
            {selectedTask && (
                <TaskDetailModal task={selectedTask} onClose={closeModal} />
            )}

            {/* Board Config Modal */}
            <BoardConfigModal
                isOpen={isConfigOpen}
                onClose={() => setIsConfigOpen(false)}
                config={config}
                onSave={saveConfig}
            />

            {/* User Profile Modal */}
            <UserProfileModal
                isOpen={isProfileOpen}
                onClose={() => setIsProfileOpen(false)}
                userId={profileUserId}
            />
        </div>
    );
};

const TaskCard = memo(({ task, onViewDetails, onProfileClick }) => {
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
                        background: PRIORITY_COLORS[task.priority] || '#ccc',
                    }}
                        title={task.priority}
                    />
                )}
            </CardHeader>
            {task.description && (
                <CardBody style={{ padding: '6px 12px 4px' }}>
                    <div style={styles.description}>{task.description}</div>
                </CardBody>
            )}
            <div style={styles.cardFooter}>
                <div style={{ display: 'flex', gap: '6px', alignItems: 'center' }}>
                    {task.story_points && (
                        <span style={styles.points}>{task.story_points} pts</span>
                    )}
                    {task.assignee_id && (
                        <span
                            style={{ ...styles.assignee, cursor: 'pointer' }}
                            onClick={() => onProfileClick(task.assignee_id)}
                            title={task.assignee || __('View Profile', 'es-scrum')}
                        >
                            👤
                        </span>
                    )}
                </div>
                <Button
                    isLink
                    style={{ fontSize: '12px', height: 'auto', padding: '0' }}
                    onClick={() => onViewDetails(task)}
                >
                    {__('View Details', 'es-scrum')}
                </Button>
            </div>
        </Card>
    );
});

const TaskDetailModal = ({ task, onClose }) => {
    return (
        <Modal
            title={task.title}
            onRequestClose={onClose}
            shouldCloseOnClickOutside={true}
            style={{ maxWidth: '680px', width: '100%' }}
        >
            {/* Meta row */}
            <div style={styles.modalMeta}>
                {task.status && (
                    <span style={styles.metaBadge}>
                        {__('Status', 'es-scrum')}: <strong>{task.status}</strong>
                    </span>
                )}
                {task.priority && (
                    <span style={{
                        ...styles.metaBadge,
                        borderLeft: `3px solid ${PRIORITY_COLORS[task.priority] || '#ccc'}`,
                    }}>
                        {__('Priority', 'es-scrum')}: <strong>{task.priority}</strong>
                    </span>
                )}
                {task.type && (
                    <span style={styles.metaBadge}>
                        {__('Type', 'es-scrum')}: <strong>{task.type}</strong>
                    </span>
                )}
                {task.story_points && (
                    <span style={styles.metaBadge}>
                        {__('Points', 'es-scrum')}: <strong>{task.story_points}</strong>
                    </span>
                )}
                {task.due_date && (
                    <span style={styles.metaBadge}>
                        {__('Due', 'es-scrum')}: <strong>{formatDate(task.due_date)}</strong>
                    </span>
                )}
                {task.sprint_id && (
                    <span style={{ ...styles.metaBadge, background: '#e8f0fb' }}>
                        {__('Sprint', 'es-scrum')} #{task.sprint_id}
                    </span>
                )}
            </div>

            {/* Description */}
            {task.description && (
                <div style={styles.modalDescription}>
                    <p style={{ margin: 0, whiteSpace: 'pre-wrap' }}>{task.description}</p>
                </div>
            )}

            <hr style={{ margin: '16px 0', borderColor: '#eee' }} />

            {/* Comments */}
            <CommentThread taskId={task.id} />
        </Modal>
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
        alignItems: 'center',
        padding: '6px 12px 10px',
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
    // Modal styles
    modalMeta: {
        display: 'flex',
        flexWrap: 'wrap',
        gap: '8px',
        marginBottom: '12px',
    },
    metaBadge: {
        fontSize: '12px',
        background: '#f0f0f1',
        padding: '4px 10px',
        borderRadius: '4px',
        color: '#333',
    },
    modalDescription: {
        background: '#f9f9f9',
        borderRadius: '4px',
        padding: '12px',
        fontSize: '13px',
        color: '#333',
        lineHeight: 1.6,
    },
};

export default ScrumBoard;
