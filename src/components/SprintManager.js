import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, TextControl, TextareaControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import SprintAnalytics from './SprintAnalytics';

const STATUS_LABELS = {
    planned: { label: 'Planned', color: '#2271b1' },
    active: { label: 'Active', color: '#00a32a' },
    completed: { label: 'Completed', color: '#757575' },
    archived: { label: 'Archived', color: '#b32d2e' },
};

const SprintManager = ({ isOpen, onClose, onSprintChange }) => {
    const [sprints, setSprints] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [saving, setSaving] = useState(false);

    // Form state
    const [formData, setFormData] = useState({
        name: '',
        start_date: '',
        end_date: '',
        goal: '',
        status: 'planned',
    });

    // Analytics
    const [analyticsId, setAnalyticsId] = useState(null);

    const fetchSprints = useCallback(() => {
        setIsLoading(true);
        apiFetch({ path: '/es-scrum/v1/sprints?per_page=50' })
            .then((data) => {
                setSprints(data);
                setIsLoading(false);
            })
            .catch((err) => {
                console.error('Failed to fetch sprints:', err);
                setIsLoading(false);
            });
    }, []);

    useEffect(() => {
        if (isOpen) fetchSprints();
    }, [isOpen, fetchSprints]);

    const resetForm = () => {
        setFormData({ name: '', start_date: '', end_date: '', goal: '', status: 'planned' });
        setEditingId(null);
        setShowForm(false);
    };

    const handleSave = () => {
        if (!formData.name.trim()) return;
        setSaving(true);

        const request = editingId
            ? apiFetch({ path: `/es-scrum/v1/sprints/${editingId}`, method: 'PATCH', data: formData })
            : apiFetch({ path: '/es-scrum/v1/sprints', method: 'POST', data: formData });

        request
            .then(() => {
                resetForm();
                fetchSprints();
                if (onSprintChange) onSprintChange();
            })
            .catch((err) => console.error('Save failed:', err))
            .finally(() => setSaving(false));
    };

    const handleEdit = (sprint) => {
        setFormData({
            name: sprint.name,
            start_date: sprint.start_date ? sprint.start_date.split(' ')[0] : '',
            end_date: sprint.end_date ? sprint.end_date.split(' ')[0] : '',
            goal: sprint.goal || '',
            status: sprint.status,
        });
        setEditingId(sprint.id);
        setShowForm(true);
        setAnalyticsId(null);
    };

    const handleArchive = (sprint) => {
        if (!window.confirm(`Archive "${sprint.name}"? Tasks will be unassigned from this sprint.`)) return;
        apiFetch({ path: `/es-scrum/v1/sprints/${sprint.id}`, method: 'PATCH', data: { status: 'archived' } })
            .then(() => {
                fetchSprints();
                if (onSprintChange) onSprintChange();
            })
            .catch((err) => console.error('Archive failed:', err));
    };

    const handleDelete = (sprint) => {
        if (!window.confirm(`Permanently delete "${sprint.name}"? This cannot be undone.`)) return;
        apiFetch({ path: `/es-scrum/v1/sprints/${sprint.id}`, method: 'DELETE' })
            .then(() => {
                fetchSprints();
                if (onSprintChange) onSprintChange();
            })
            .catch((err) => console.error('Delete failed:', err));
    };

    if (!isOpen) return null;

    const activeSprints = sprints.filter(s => s.status !== 'archived');
    const archivedSprints = sprints.filter(s => s.status === 'archived');

    return (
        <div style={styles.overlay} onClick={onClose}>
            <div style={styles.panel} onClick={(e) => e.stopPropagation()}>
                {/* Header */}
                <div style={styles.header}>
                    <h2 style={{ margin: 0 }}>{__('Sprint Manager', 'es-scrum')}</h2>
                    <Button icon="no-alt" onClick={onClose} label="Close" />
                </div>

                {/* Create / Edit Form */}
                {showForm ? (
                    <div style={styles.form}>
                        <h3 style={{ marginTop: 0 }}>{editingId ? 'Edit Sprint' : 'New Sprint'}</h3>
                        <TextControl
                            label="Name"
                            value={formData.name}
                            onChange={(val) => setFormData({ ...formData, name: val })}
                            placeholder="Sprint 1"
                        />
                        <div style={{ display: 'flex', gap: '12px' }}>
                            <div style={{ flex: 1 }}>
                                <label style={styles.label}>Start Date</label>
                                <input
                                    type="date"
                                    value={formData.start_date}
                                    onChange={(e) => setFormData({ ...formData, start_date: e.target.value })}
                                    style={styles.dateInput}
                                />
                            </div>
                            <div style={{ flex: 1 }}>
                                <label style={styles.label}>End Date</label>
                                <input
                                    type="date"
                                    value={formData.end_date}
                                    onChange={(e) => setFormData({ ...formData, end_date: e.target.value })}
                                    style={styles.dateInput}
                                />
                            </div>
                        </div>
                        {editingId && (
                            <div style={{ marginTop: '8px' }}>
                                <label style={styles.label}>Status</label>
                                <select
                                    value={formData.status}
                                    onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                                    style={styles.dateInput}
                                >
                                    <option value="planned">Planned</option>
                                    <option value="active">Active</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        )}
                        <TextareaControl
                            label="Goal"
                            value={formData.goal}
                            onChange={(val) => setFormData({ ...formData, goal: val })}
                            placeholder="What we aim to achieve this sprint…"
                        />
                        <div style={{ display: 'flex', gap: '8px' }}>
                            <Button variant="primary" onClick={handleSave} isBusy={saving} disabled={saving}>
                                {editingId ? 'Update' : 'Create'}
                            </Button>
                            <Button variant="tertiary" onClick={resetForm}>Cancel</Button>
                        </div>
                    </div>
                ) : (
                    <Button
                        variant="primary"
                        onClick={() => { setShowForm(true); setAnalyticsId(null); }}
                        style={{ margin: '16px' }}
                    >
                        + New Sprint
                    </Button>
                )}

                {/* Analytics panel */}
                {analyticsId && (
                    <div style={{ padding: '0 16px 16px' }}>
                        <SprintAnalytics sprintId={analyticsId} />
                    </div>
                )}

                {/* Sprint list */}
                <div style={styles.list}>
                    {isLoading ? (
                        <div style={{ textAlign: 'center', padding: '20px' }}><Spinner /></div>
                    ) : (
                        <>
                            {activeSprints.length === 0 && (
                                <p style={styles.empty}>No sprints yet. Create one to get started.</p>
                            )}
                            {activeSprints.map((sprint) => (
                                <SprintCard
                                    key={sprint.id}
                                    sprint={sprint}
                                    onEdit={() => handleEdit(sprint)}
                                    onArchive={() => handleArchive(sprint)}
                                    onDelete={() => handleDelete(sprint)}
                                    onAnalytics={() => setAnalyticsId(analyticsId === sprint.id ? null : sprint.id)}
                                    isAnalyticsOpen={analyticsId === sprint.id}
                                />
                            ))}

                            {archivedSprints.length > 0 && (
                                <>
                                    <h4 style={{ padding: '0 16px', color: '#757575' }}>Archived</h4>
                                    {archivedSprints.map((sprint) => (
                                        <SprintCard
                                            key={sprint.id}
                                            sprint={sprint}
                                            onDelete={() => handleDelete(sprint)}
                                            onAnalytics={() => setAnalyticsId(analyticsId === sprint.id ? null : sprint.id)}
                                            isAnalyticsOpen={analyticsId === sprint.id}
                                            isArchived
                                        />
                                    ))}
                                </>
                            )}
                        </>
                    )}
                </div>
            </div>
        </div>
    );
};

const SprintCard = ({ sprint, onEdit, onArchive, onDelete, onAnalytics, isAnalyticsOpen, isArchived }) => {
    const statusInfo = STATUS_LABELS[sprint.status] || STATUS_LABELS.planned;
    const dates = [];
    if (sprint.start_date) dates.push(sprint.start_date.split(' ')[0]);
    if (sprint.end_date) dates.push(sprint.end_date.split(' ')[0]);

    return (
        <div style={{
            ...styles.card,
            borderLeft: `4px solid ${statusInfo.color}`,
            opacity: isArchived ? 0.7 : 1,
        }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                <div style={{ flex: 1 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <strong>{sprint.name}</strong>
                        <span style={{
                            ...styles.badge,
                            background: statusInfo.color,
                        }}>
                            {statusInfo.label}
                        </span>
                    </div>
                    {dates.length > 0 && (
                        <div style={styles.dates}>{dates.join(' → ')}</div>
                    )}
                    {sprint.goal && <div style={styles.goal}>{sprint.goal}</div>}
                </div>
            </div>
            <div style={{ display: 'flex', gap: '4px', marginTop: '8px' }}>
                <Button
                    variant="tertiary"
                    size="small"
                    onClick={onAnalytics}
                    style={{ fontSize: '12px' }}
                >
                    {isAnalyticsOpen ? 'Hide Stats' : 'Stats'}
                </Button>
                {!isArchived && onEdit && (
                    <Button variant="tertiary" size="small" onClick={onEdit} style={{ fontSize: '12px' }}>
                        Edit
                    </Button>
                )}
                {!isArchived && onArchive && (
                    <Button variant="tertiary" size="small" onClick={onArchive} style={{ fontSize: '12px', color: '#b32d2e' }}>
                        Archive
                    </Button>
                )}
                {isArchived && onDelete && (
                    <Button variant="tertiary" size="small" onClick={onDelete} style={{ fontSize: '12px', color: '#b32d2e' }}>
                        Delete
                    </Button>
                )}
            </div>
        </div>
    );
};

const styles = {
    overlay: {
        position: 'fixed',
        top: 0,
        right: 0,
        bottom: 0,
        left: 0,
        background: 'rgba(0,0,0,0.3)',
        zIndex: 100000,
        display: 'flex',
        justifyContent: 'flex-end',
    },
    panel: {
        width: '420px',
        maxWidth: '90vw',
        background: '#fff',
        height: '100%',
        overflowY: 'auto',
        boxShadow: '-4px 0 12px rgba(0,0,0,0.15)',
        display: 'flex',
        flexDirection: 'column',
    },
    header: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        padding: '16px',
        borderBottom: '1px solid #ddd',
        position: 'sticky',
        top: 0,
        background: '#fff',
        zIndex: 1,
    },
    form: {
        padding: '16px',
        borderBottom: '1px solid #eee',
        background: '#f9f9f9',
    },
    list: {
        flex: 1,
    },
    card: {
        margin: '8px 16px',
        padding: '12px',
        background: '#f9f9f9',
        borderRadius: '4px',
        border: '1px solid #e0e0e0',
    },
    badge: {
        color: '#fff',
        fontSize: '11px',
        padding: '2px 8px',
        borderRadius: '10px',
        fontWeight: 600,
    },
    dates: {
        fontSize: '12px',
        color: '#757575',
        marginTop: '4px',
    },
    goal: {
        fontSize: '13px',
        color: '#555',
        marginTop: '6px',
        fontStyle: 'italic',
    },
    empty: {
        textAlign: 'center',
        color: '#757575',
        padding: '20px',
    },
    label: {
        display: 'block',
        fontSize: '11px',
        fontWeight: 500,
        marginBottom: '4px',
        textTransform: 'uppercase',
    },
    dateInput: {
        width: '100%',
        padding: '6px 8px',
        border: '1px solid #8c8f94',
        borderRadius: '4px',
        fontSize: '14px',
    },
};

export default SprintManager;
