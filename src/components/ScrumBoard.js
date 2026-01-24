import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Spinner, Button, Card, CardBody, CardHeader, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import CommentThread from './CommentThread';

const ScrumBoard = () => {
    const [tasks, setTasks] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [selectedTask, setSelectedTask] = useState(null);

    useEffect(() => {
        apiFetch({ path: '/es-scrum/v1/tasks' })
            .then((data) => {
                setTasks(data);
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

    if (isLoading) {
        return <Spinner />;
    }

    if (error) {
        return <div className="notice notice-error"><p>{error}</p></div>;
    }

    const columns = {
        backlog: [],
        'in-progress': [],
        done: []
    };

    tasks.forEach(task => {
        const status = task.status || 'backlog';
        if (columns[status]) {
            columns[status].push(task);
        } else {
            // Fallback for unknown statuses
            if (!columns.backlog) columns.backlog = [];
            columns.backlog.push(task);
        }
    });

    return (
        <div className="es-scrum-board">
            <div style={{ display: 'flex', gap: '20px' }}>
                {Object.keys(columns).map(status => (
                    <div key={status} style={{ flex: 1, background: '#f0f0f1', padding: '10px', borderRadius: '4px' }}>
                        <h3 style={{ textTransform: 'capitalize' }}>{status.replace('-', ' ')}</h3>
                        {columns[status].map(task => (
                            <Card key={task.id} style={{ marginBottom: '10px' }}>
                                <CardHeader>
                                    <strong>{task.title}</strong>
                                </CardHeader>
                                <CardBody>
                                    <p>{task.description}</p>
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
        </div>
    );
};

export default ScrumBoard;
