import { useState, useEffect, memo, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Spinner, Button, Card, CardBody, CardHeader, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import CommentThread from './CommentThread';
import BoardConfigModal from './BoardConfigModal';
import UserProfileModal from './UserProfileModal';
import { defaultConfig } from '../utils/defaultConfig';

// Memoized Task Card
const TaskCard = memo(({ task, onProfileClick, onViewDetails }) => {
    const [showComments, setShowComments] = useState(false);
    const [comments, setComments] = useState([]);
    const [isLoadingComments, setIsLoadingComments] = useState(false);

    const toggleComments = () => {
        if (!showComments && comments.length === 0) {
            setIsLoadingComments(true);
            apiFetch({ path: `/es-scrum/v1/comments?task_id=${task.id}&per_page=5` })
                .then((data) => {
                    setComments(data);
                    setIsLoadingComments(false);
                })
                .catch((err) => {
                    console.error(err);
                    setIsLoadingComments(false);
                });
        }
        setShowComments(!showComments);
    };

    return (
        <Card style={{ marginBottom: '10px' }}>
            <CardHeader>
                <strong>{task.title}</strong>
            </CardHeader>
            <CardBody>
                {task.description}
                <div style={{ marginTop: '5px', fontSize: '0.85em', color: 'var(--secondary-text)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div>
                        {task.type}
                        {task.assignee_id && (
                            <span
                                style={{ marginLeft: '10px', cursor: 'pointer', color: 'var(--link-color)'}}
                                onClick={() => onProfileClick(task.assignee_id)}
                            >
                                @{task.assignee}
                            </span>
                        )}
                    </div>
                    <Button isSmall variant="tertiary" onClick={toggleComments}>
                        {showComments ? __('Hide Comments', 'es-scrum') : __('Show Comments', 'es-scrum')}
                    </Button>
                </div>
                {/* View Details Button added for Head compatibility */}
                <Button isLink onClick={() => onViewDetails(task)} style={{ marginTop: '10px', display: 'block' }}>
                    {__('View Details', 'es-scrum')}
                </Button>
                {showComments && (
                    <div style={{ marginTop: '10px', paddingTop: '10px', borderTop: '1px solid var(--border-color)' }}>
                        {isLoadingComments ? (
                            <Spinner />
                        ) : comments.length === 0 ? (
                            <p style={{ fontStyle: 'italic', color: 'var(--secondary-text)' }}>{__('No comments yet.', 'es-scrum')}</p>
                        ) : (
                            <ul style={{ listStyle: 'none', margin: 0, padding: 0 }}>
                                {comments.map(comment => (
                                    <li key={comment.id} style={{ marginBottom: '8px', fontSize: '0.9em' }}>
                                        <strong>User {comment.user_id}:</strong> {comment.body}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}
            </CardBody>
        </Card>
    );
});

// Memoized Column
const BoardColumn = memo(({ col, config, onProfileClick, onViewDetails }) => {
    return (
        <div style={{ minWidth: '300px', flex: 1, background: 'var(--card-bg)', padding: '10px', borderRadius: '4px', border: `${config.theme === 'contrast' && 'var(--border-color)'}` }}>
            <h3 style={{ textTransform: 'capitalize', color: 'var(--text-color)' ,borderBottom: '3px solid var(--border-color)' , paddingBottom: '5px'}}>
                {col.title} <span style={{ fontSize: '0.8em', color: 'var(--text-color)' }}>({col.tasks.length})</span>
            </h3>
            {col.tasks.map(task => (
                <TaskCard key={task.id} task={task} onProfileClick={onProfileClick} onViewDetails={onViewDetails} />
            ))}
        </div>
    );
});

const ScrumBoard = () => {
    const [tasks, setTasks] = useState([]);
    const [config, setConfig] = useState(defaultConfig);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [selectedTask, setSelectedTask] = useState(null);
    const [isConfigOpen, setIsConfigOpen] = useState(false);

    // DC-18: Profile Modal State
    const [isProfileOpen, setIsProfileOpen] = useState(false);
    const [profileUserId, setProfileUserId] = useState(null);
    const [currentUserId, setCurrentUserId] = useState(null);

    useEffect(() => {
        Promise.all([
            apiFetch({ path: '/es-scrum/v1/tasks?per_page=100' }), // Fetch more tasks
            apiFetch({ path: '/es-scrum/v1/config' }).catch(() => null),
            apiFetch({ path: '/wp/v2/users/me' }).catch(() => null)
        ])
            .then(([tasksData, configData, userData]) => {
                setTasks(tasksData);
                if (configData) {
                    setConfig(configData);
                }
                if (userData) {
                    setCurrentUserId(userData.id);
                }
                setIsLoading(false);
            })
            .catch((err) => {
                console.error(err);
                setError(err.message);
                setIsLoading(false);
            });
    }, []);

    const openModal = (task) => {
        setSelectedTask(task);
        setIsModalOpen(true);
    };

    const closeModal = () => {
        setIsModalOpen(false);
        setSelectedTask(null);
    };

    const saveConfig = (newConfig) => {
        setIsLoading(true);
        apiFetch({
            path: '/es-scrum/v1/config',
            method: 'POST',
            data: newConfig
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

    const openMyProfile = () => {
        if (currentUserId) {
            setProfileUserId(currentUserId);
            setIsProfileOpen(true);
        }
    };

    const handleProfileClick = useCallback((userId) => {
        setProfileUserId(userId);
        setIsProfileOpen(true);
    }, []);

    if (isLoading) {
        return <Spinner />;
    }

    if (error) {
        return <div className="notice notice-error"><p>{error}</p></div>;
    }

    // Organize tasks into columns based on config
    const boardColumns = config.columns.map(col => ({
        ...col,
        tasks: []
    }));

    const columnMap = {};
    boardColumns.forEach((col, index) => {
        columnMap[col.id] = index;
    });

    const unmappedTasks = [];
    tasks.forEach(task => {
        const status = task.status || 'backlog';
        if (columnMap.hasOwnProperty(status)) {
            boardColumns[columnMap[status]].tasks.push(task);
        } else {
            unmappedTasks.push(task);
        }
    });

    if (unmappedTasks.length > 0) {
        if (columnMap['backlog'] !== undefined) {
            boardColumns[columnMap['backlog']].tasks.push(...unmappedTasks);
        } else if (boardColumns.length > 0) {
            boardColumns[0].tasks.push(...unmappedTasks);
        }
    }

    return (
        <div
            className="es-scrum-board"
            style={{
            background: 'var(--bg-color)',
            color: 'var(--text-color)'
            }}
        >
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                <h2   style={{color: ['dark', 'contrast'].includes(config.theme) ? 'var(--text-color)': undefined}}>{__('Board', 'es-scrum')}</h2>
                <div style={{ display: 'flex', gap: '10px' }}>
                    <Button isSecondary onClick={openMyProfile}>
                        {__('My Profile', 'es-scrum')}
                    </Button>
                    <Button isSecondary onClick={() => setIsConfigOpen(true)}>
                        {__('Customize Board', 'es-scrum')}
                    </Button>
                </div>
            </div>

            <div style={{ display: 'flex', gap: '20px', overflowX: 'auto', paddingBottom: '20px' }}>
                {boardColumns.map(col => (
                    <BoardColumn
                        key={col.id}
                        col={col}
                        config={config}
                        onProfileClick={handleProfileClick}
                        onViewDetails={openModal}
                    />
                ))}
            </div>
            {isModalOpen && selectedTask && (
                <Modal title={selectedTask.title} onRequestClose={closeModal} shouldCloseOnClickOutside={true}>
                    <p>{selectedTask.description}</p>
                    <hr />
                    <CommentThread taskId={selectedTask.id} />
                </Modal>
            )}

            <BoardConfigModal
                isOpen={isConfigOpen}
                onClose={() => setIsConfigOpen(false)}
                config={config}
                onSave={saveConfig}
            />

            <UserProfileModal
                isOpen={isProfileOpen}
                onClose={() => setIsProfileOpen(false)}
                userId={profileUserId}
            />
        </div>
    );
};

export default ScrumBoard;
