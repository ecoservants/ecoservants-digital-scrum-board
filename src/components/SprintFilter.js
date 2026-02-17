import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const SprintFilter = ({ selectedSprintId, onSprintChange }) => {
    const [sprints, setSprints] = useState([]);

    const fetchSprints = () => {
        apiFetch({ path: '/es-scrum/v1/sprints?per_page=50' })
            .then((data) => {
                // Only show active/planned sprints in the filter
                const filterable = data.filter(s => s.status === 'active' || s.status === 'planned');
                setSprints(filterable);
            })
            .catch((err) => console.error('Failed to fetch sprints for filter:', err));
    };

    useEffect(() => {
        fetchSprints();
    }, []);

    // Expose refresh method via key prop change
    useEffect(() => {
        fetchSprints();
    }, [selectedSprintId]);

    const options = [
        { label: __('All Tasks', 'es-scrum'), value: '' },
        ...sprints.map((s) => ({
            label: `${s.name}${s.status === 'active' ? ' ●' : ''}`,
            value: String(s.id),
        })),
    ];

    const selectedSprint = sprints.find(s => String(s.id) === String(selectedSprintId));

    return (
        <div style={styles.container}>
            <div style={styles.filterRow}>
                <SelectControl
                    label={__('Sprint', 'es-scrum')}
                    value={selectedSprintId || ''}
                    options={options}
                    onChange={(val) => onSprintChange(val || null)}
                    __nextHasNoMarginBottom
                    style={{ minWidth: '200px' }}
                />
            </div>
            {selectedSprint && selectedSprint.goal && (
                <div style={styles.goal}>
                    <strong>Goal:</strong> {selectedSprint.goal}
                </div>
            )}
        </div>
    );
};

const styles = {
    container: {
        marginBottom: '16px',
    },
    filterRow: {
        display: 'flex',
        alignItems: 'flex-end',
        gap: '12px',
    },
    goal: {
        marginTop: '8px',
        padding: '8px 12px',
        background: '#f0f6fc',
        border: '1px solid #c3d1e0',
        borderRadius: '4px',
        fontSize: '13px',
        color: '#1d2327',
    },
};

export default SprintFilter;
