/**
 * Default board configuration
 */
export const defaultConfig = {
    columns: [
        { id: 'backlog', title: 'Backlog', type: 'system' },
        { id: 'in-progress', title: 'In Progress', type: 'system' },
        { id: 'done', title: 'Done', type: 'system' }
    ],
    taskTypes: [
        { id: 'task', label: 'Task', color: '#f0f0f1' },
        { id: 'bug', label: 'Bug', color: '#cf2e2e' },
        { id: 'story', label: 'Story', color: '#00a32a' }
    ],
    theme: 'light'
};
