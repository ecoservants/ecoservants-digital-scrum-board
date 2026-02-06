import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Spinner, Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import BoardConfigModal from './BoardConfigModal';
import { defaultConfig } from '../utils/defaultConfig';

const ScrumBoard = () => {
    const [tasks, setTasks] = useState([]);
    const [config, setConfig] = useState(defaultConfig);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);
    const [isConfigOpen, setIsConfigOpen] = useState(false);

    useEffect(() => {
        Promise.all([
            apiFetch({ path: '/es-scrum/v1/tasks' }),
            apiFetch({ path: '/es-scrum/v1/config' }).catch(() => null)
        ])
            .then(([tasksData, configData]) => {
                setTasks(tasksData);
                if (configData) {
                    setConfig(configData);
                }
                setIsLoading(false);
            })
            .catch((err) => {
                console.error(err);
                setError(err.message);
                setIsLoading(false);
            });
    }, []);

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

    // Fallback column for tasks with unknown status
    // tasks.forEach(task => {
    //     // ... logic to put task in correct column
    //     // For now simple matching on ID. New columns match status slug.
    // });

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
                <Button isSecondary onClick={() => setIsConfigOpen(true)}>
                    {__('Customize Board', 'es-scrum')}
                </Button>
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
                                    {task.description}
                                    <div style={{ marginTop: '5px', fontSize: '0.85em', color: '#555' }}>
                                        {task.type}
                                    </div>
                                </CardBody>
                            </Card>
                        ))}
                    </div>
                ))}
            </div>

            <BoardConfigModal
                isOpen={isConfigOpen}
                onClose={() => setIsConfigOpen(false)}
                config={config}
                onSave={saveConfig}
            />
        </div>
    );
};

export default ScrumBoard;
