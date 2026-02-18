import { useState, useEffect } from '@wordpress/element';
import { Modal, TabPanel, Spinner, Button, Card, CardBody } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const UserProfileModal = ({ userId, isOpen, onClose }) => {
    const [data, setData] = useState(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);

    // Pagination State
    const [tasks, setTasks] = useState([]);
    const [activity, setActivity] = useState([]);
    const [tasksPage, setTasksPage] = useState(1);
    const [activityPage, setActivityPage] = useState(1);
    const [hasMoreTasks, setHasMoreTasks] = useState(false);
    const [hasMoreActivity, setHasMoreActivity] = useState(false);
    const [isLoadingMoreTasks, setIsLoadingMoreTasks] = useState(false);
    const [isLoadingMoreActivity, setIsLoadingMoreActivity] = useState(false);

    useEffect(() => {
        if (isOpen && userId) {
            setIsLoading(true);
            // Reset pagination
            setTasksPage(1);
            setActivityPage(1);
            setTasks([]);
            setActivity([]);

            apiFetch({ path: `/es-scrum/v1/users/${userId}/profile`, parse: false })
                .then(res => res.json())
                .then((response) => {
                    setData(response);
                    setTasks(response.tasks || []);
                    setActivity(response.activity || []);

                    // Use Headers if accessible or fallback to reliable count
                    // Since the previous response was just JSON, let's rely on the separate tasks endpoint for total count
                    // OR: check if total_assigned > 5
                    setHasMoreTasks((response.stats?.total_assigned || 0) > 5);

                    // For activity, we might need a similar total check or just rely on length for now if headers aren't easy in this composite call
                    setHasMoreActivity(response.activity && response.activity.length === 5); // Fallback for activity until separate count is available

                    setIsLoading(false);
                })
                .catch((err) => {
                    console.error(err);
                    setError(err.message);
                    setIsLoading(false);
                });
        }
    }, [isOpen, userId]);

    const loadMoreTasks = () => {
        setIsLoadingMoreTasks(true);
        const nextPage = tasksPage + 1;
        apiFetch({ path: `/es-scrum/v1/users/${userId}/tasks?page=${nextPage}&per_page=5`, parse: false })
            .then(res => {
                const totalPages = parseInt(res.headers.get('X-WP-TotalPages'), 10);
                return res.json().then(data => ({ data, totalPages }));
            })
            .then(({ data, totalPages }) => {
                setTasks([...tasks, ...data]);
                setTasksPage(nextPage);
                setHasMoreTasks(nextPage < totalPages);
                setIsLoadingMoreTasks(false);
            })
            .catch((err) => {
                console.error(err);
                setIsLoadingMoreTasks(false);
            });
    };

    const loadMoreActivity = () => {
        setIsLoadingMoreActivity(true);
        const nextPage = activityPage + 1;
        apiFetch({ path: `/es-scrum/v1/users/${userId}/activity?page=${nextPage}&per_page=5`, parse: false })
            .then(res => {
                const totalPages = parseInt(res.headers.get('X-WP-TotalPages'), 10);
                return res.json().then(data => ({ data, totalPages }));
            })
            .then(({ data, totalPages }) => {
                setActivity([...activity, ...data]);
                setActivityPage(nextPage);
                setHasMoreActivity(nextPage < totalPages);
                setIsLoadingMoreActivity(false);
            })
            .catch((err) => {
                console.error(err);
                setIsLoadingMoreActivity(false);
            });
    };

    if (!isOpen) return null;

    return (
        <Modal
            title={__('User Profile', 'es-scrum')}
            onRequestClose={onClose}
            className="es-scrum-profile-modal"
            style={{ minWidth: '600px', maxWidth: '800px' }}
        >
            {isLoading ? (
                <div style={{ display: 'flex', justifyContent: 'center', padding: '50px' }}>
                    <Spinner />
                </div>
            ) : error ? (
                <div className="notice notice-error"><p>{error}</p></div>
            ) : data ? (
                <div>
                    {/* Header Section */}
                    <div style={{ display: 'flex', alignItems: 'center', gap: '20px', marginBottom: '20px', paddingBottom: '20px', borderBottom: '1px solid #ddd' }}>
                        <img
                            src={data.user.avatar_url}
                            alt={data.user.display_name}
                            style={{ width: '80px', height: '80px', borderRadius: '50%' }}
                        />
                        <div>
                            <h2 style={{ margin: 0 }}>{data.user.display_name}</h2>
                            <p style={{ margin: '5px 0', color: '#666' }}>{data.user.email}</p>
                            <div style={{ display: 'flex', gap: '10px' }}>
                                <span className="es-badge" style={{ background: '#e5e5e5', padding: '2px 8px', borderRadius: '12px', fontSize: '0.85em' }}>
                                    {data.user.program_group || __('No Program', 'es-scrum')}
                                </span>
                                {data.user.roles.map(role => (
                                    <span key={role} className="es-badge" style={{ background: '#dce6f1', padding: '2px 8px', borderRadius: '12px', fontSize: '0.85em' }}>
                                        {role}
                                    </span>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Stats Section */}
                    <div style={{ display: 'flex', gap: '15px', marginBottom: '20px' }}>
                        <StatCard label={__('Total Tasks', 'es-scrum')} value={data.stats.total_assigned} />
                        <StatCard label={__('In Progress', 'es-scrum')} value={data.stats.in_progress} color="#f0b800" />
                        <StatCard label={__('Completed', 'es-scrum')} value={data.stats.completed} color="#46b450" />
                        <StatCard label={__('Overdue', 'es-scrum')} value={data.stats.overdue} color="#dc3232" />
                    </div>

                    {/* Tabs for Tasks & Activity */}
                    <TabPanel
                        className="es-scrum-profile-tabs"
                        activeClass="is-active"
                        tabs={[
                            {
                                name: 'tasks',
                                title: __('Assigned Tasks', 'es-scrum'),
                                className: 'es-profile-tab-tasks',
                            },
                            {
                                name: 'activity',
                                title: __('Activity Log', 'es-scrum'),
                                className: 'es-profile-tab-activity',
                            },
                        ]}
                    >
                        {(tab) => (
                            <div style={{ marginTop: '20px', maxHeight: '400px', overflowY: 'auto', paddingRight: '10px' }}>
                                {tab.name === 'tasks' && (
                                    <div>
                                        {tasks.length === 0 ? (
                                            <p>{__('No assigned tasks found.', 'es-scrum')}</p>
                                        ) : (
                                            <>
                                                {tasks.map(task => (
                                                    <Card key={task.id} style={{ marginBottom: '10px', boxShadow: 'none', border: '1px solid #e0e0e0' }}>
                                                        <CardBody style={{ padding: '12px' }}>
                                                            <strong style={{ fontSize: '1.1em' }}>{task.title}</strong>
                                                            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.9em', color: '#666', marginTop: '5px' }}>
                                                                <span>{task.status}</span>
                                                                <span>{new Date(task.created_at).toLocaleDateString()}</span>
                                                            </div>
                                                        </CardBody>
                                                    </Card>
                                                ))}
                                                {hasMoreTasks && (
                                                    <Button isSecondary onClick={loadMoreTasks} disabled={isLoadingMoreTasks} style={{ width: '100%' }}>
                                                        {isLoadingMoreTasks ? <Spinner /> : __('Load More Tasks', 'es-scrum')}
                                                    </Button>
                                                )}
                                            </>
                                        )}
                                    </div>
                                )}

                                {tab.name === 'activity' && (
                                    <div>
                                        {activity.length === 0 ? (
                                            <p>{__('No recent activity.', 'es-scrum')}</p>
                                        ) : (
                                            <>
                                                <ul style={{ listStyle: 'none', margin: 0, padding: 0, marginBottom: '10px' }}>
                                                    {activity.map(act => (
                                                        <li key={act.id} style={{ padding: '10px 0', borderBottom: '1px solid #f0f0f0' }}>
                                                            <strong>{act.action}</strong>
                                                            <span style={{ color: '#666', marginLeft: '8px' }}>
                                                                {act.from_value} &rarr; {act.to_value}
                                                            </span>
                                                            <div style={{ fontSize: '0.85em', color: '#999' }}>
                                                                {new Date(act.created_at).toLocaleString()}
                                                            </div>
                                                        </li>
                                                    ))}
                                                </ul>
                                                {hasMoreActivity && (
                                                    <Button isSecondary onClick={loadMoreActivity} disabled={isLoadingMoreActivity} style={{ width: '100%' }}>
                                                        {isLoadingMoreActivity ? <Spinner /> : __('Load More Activity', 'es-scrum')}
                                                    </Button>
                                                )}
                                            </>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}
                    </TabPanel>
                </div>
            ) : null}
        </Modal>
    );
};

const StatCard = ({ label, value, color = '#333' }) => (
    <div style={{
        flex: 1,
        textAlign: 'center',
        padding: '15px',
        background: '#f9f9f9',
        borderRadius: '8px',
        borderTop: `3px solid ${color}`
    }}>
        <div style={{ fontSize: '2em', fontWeight: 'bold', color: color }}>{value}</div>
        <div style={{ fontSize: '0.9em', color: '#666', textTransform: 'uppercase' }}>{label}</div>
    </div>
);

export default UserProfileModal;
