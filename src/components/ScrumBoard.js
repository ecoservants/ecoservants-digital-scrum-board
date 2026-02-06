import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Spinner, Button, Card, CardBody, CardHeader, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import CommentThread from './CommentThread';
import BoardConfigModal from './BoardConfigModal';
import UserProfileModal from './UserProfileModal';
import { defaultConfig } from '../utils/defaultConfig';

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
            apiFetch({ path: '/es-scrum/v1/tasks' }),
            apiFetch({ path: '/es-scrum/v1/config' }).catch(() => null),
            apiFetch({ path: '/wp/v2/users/me' }).catch(() => null) // Get current user ID
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

<<<<<<< HEAD
    const openModal = (task) => {
        setSelectedTask(task);
        setIsModalOpen(true);
    };

    const closeModal = () => {
        setIsModalOpen(false);
        setSelectedTask(null);
=======
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
>>>>>>> feat(DC-12): Implement customizable boards with backend config and frontend modal
    };

    const openMyProfile = () => {
        if (currentUserId) {
            setProfileUserId(currentUserId);
            setIsProfileOpen(true);
        }
    };

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

    // Map for quick lookup
    const columnMap = {};
    boardColumns.forEach((col, index) => {
        columnMap[col.id] = index;
    });

    // Distribute tasks
    const unmappedTasks = [];
    tasks.forEach(task => {
        const status = task.status || 'backlog';
        if (columnMap.hasOwnProperty(status)) {
            boardColumns[columnMap[status]].tasks.push(task);
        } else {
            unmappedTasks.push(task);
        }
    });

    // If unmapped tasks exist, maybe show them in first column or a special one?
    // For now, let's put them in the first column as a fallback or "Backlog" if exists.
    if (unmappedTasks.length > 0) {
        if (columnMap['backlog'] !== undefined) {
            boardColumns[columnMap['backlog']].tasks.push(...unmappedTasks);
        } else if (boardColumns.length > 0) {
            boardColumns[0].tasks.push(...unmappedTasks);
        }
    }

    return (
        <div className="es-scrum-board">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                <h2>{__('Board', 'es-scrum')}</h2>
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
                    <div key={col.id} style={{ minWidth: '300px', flex: 1, background: '#f0f0f1', padding: '10px', borderRadius: '4px' }}>
                        <h3 style={{ textTransform: 'capitalize', borderBottom: `3px solid ${config.theme === 'dark' ? '#333' : '#ddd'}`, paddingBottom: '5px' }}>
                            {col.title} <span style={{ fontSize: '0.8em', color: '#666' }}>({col.tasks.length})</span>
                        </h3>
                        {col.tasks.map(task => (
                            <Card key={task.id} style={{ marginBottom: '10px' }}>
                                <CardHeader>
                                    <strong>{task.title}</strong>
                                </CardHeader>
                                <CardBody>
                                    <p>{task.description}</p>
                                    <div style={{ marginTop: '5px', fontSize: '0.85em', color: '#555' }}>
                                        {task.type}
                                        {task.assignee_id && (
                                            <span
                                                style={{ marginLeft: '10px', cursor: 'pointer', color: '#0073aa' }}
                                                onClick={() => {
                                                    setProfileUserId(task.assignee_id);
                                                    setIsProfileOpen(true);
                                                }}
                                            >
                                                @{task.assignee}
                                            </span>
                                        )}
                                    </div>
                                    <Button isLink onClick={() => openModal(task)}>View Details</Button>
                                </CardBody>
                            </Card>
                        ))}
                    </div>
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
