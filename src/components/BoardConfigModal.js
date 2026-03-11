import { useState, useEffect } from '@wordpress/element';
import { Modal, Button, TextControl, SelectControl, ColorPalette, PanelBody, PanelRow } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { applyTheme, saveTheme, loadTheme } from "../utils/themeManager";

const BoardConfigModal = ({ isOpen, onClose, config, onSave }) => {
    const [localConfig, setLocalConfig] = useState(config);

    useEffect(() => {
        setLocalConfig({
            ...config,
            theme: loadTheme()
        });
    }, [config]);

    if (!isOpen) return null;

    const updateColumn = (index, key, value) => {
        const newColumns = [...localConfig.columns];
        newColumns[index] = { ...newColumns[index], [key]: value };
        setLocalConfig({ ...localConfig, columns: newColumns });
    };

    const addColumn = () => {
        const id = 'col_' + Date.now();
        const newColumns = [...localConfig.columns, { id, title: 'New Column', type: 'custom' }];
        setLocalConfig({ ...localConfig, columns: newColumns });
    };

    const removeColumn = (index) => {
        const newColumns = localConfig.columns.filter((_, i) => i !== index);
        setLocalConfig({ ...localConfig, columns: newColumns });
    };

    const moveColumn = (index, direction) => {
        const newColumns = [...localConfig.columns];
        const targetIndex = index + direction;
        if (targetIndex >= 0 && targetIndex < newColumns.length) {
            [newColumns[index], newColumns[targetIndex]] = [newColumns[targetIndex], newColumns[index]];
            setLocalConfig({ ...localConfig, columns: newColumns });
        }
    };

    const handleSave = () => {
        onSave(localConfig);
    
        // Apply theme only when saved
        applyTheme(localConfig.theme);
        saveTheme(localConfig.theme);
    
        // Save to WordPress
        fetch("/wp-json/es-scrum/v1/user-theme", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ theme: localConfig.theme })
        });
    
        onClose();
    };

    return (
        <Modal title={__('Board Settings', 'es-scrum')} onRequestClose={onClose}>
            <PanelBody title={__('Columns', 'es-scrum')} initialOpen={true}>
                {localConfig.columns.map((col, index) => (
                    <div key={col.id} style={{ display: 'flex', alignItems: 'center', marginBottom: '10px', gap: '10px' }}>
                        <TextControl
                            value={col.title}
                            onChange={(val) => updateColumn(index, 'title', val)}
                            style={{ flex: 1, marginBottom: 0 }}
                        />
                        <Button isSmall onClick={() => moveColumn(index, -1)} disabled={index === 0}>↑</Button>
                        <Button isSmall onClick={() => moveColumn(index, 1)} disabled={index === localConfig.columns.length - 1}>↓</Button>
                        <Button isDestructive isSmall onClick={() => removeColumn(index)}>X</Button>
                    </div>
                ))}
                <Button isSecondary onClick={addColumn}>{__('Add Column', 'es-scrum')}</Button>
            </PanelBody>

            <PanelBody title={__('Theme', 'es-scrum')} initialOpen={false}>
                <SelectControl
                    label={__('Color Theme', 'es-scrum')}
                    value={localConfig.theme}
                    options={[
                        { label: 'Light', value: 'light' },
                        { label: 'Dark', value: 'dark' },
                        { label: 'EcoServants', value: 'eco' },
                        { label: 'High Contrast', value: 'contrast' },
                    ]}
                    onChange={(val) => {
                        setLocalConfig({ ...localConfig, theme: val });
                    }}
                />
            </PanelBody>

            <PanelBody title={__('Task Types', 'es-scrum')} initialOpen={false}>
                <p>{__('Customize available task types.', 'es-scrum')}</p>
                {/*  Simplified Task Type Editor for now */}
                {localConfig.taskTypes && localConfig.taskTypes.map((type, index) => (
                    <div key={type.id || index} style={{ marginBottom: '5px' }}>
                        <strong>{type.label}</strong> <span style={{ display: 'inline-block', width: '10px', height: '10px', backgroundColor: type.color }}></span>
                    </div>
                ))}
                <p style={{ fontSize: '11px', color: '#666' }}>* Task type adding not fully implemented in UI demo</p>
            </PanelBody>

            <div style={{ marginTop: '20px', display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                <Button isSecondary onClick={onClose}>{__('Cancel', 'es-scrum')}</Button>
                <Button isPrimary onClick={handleSave}>{__('Save Changes', 'es-scrum')}</Button>
            </div>
        </Modal>
    );
};

export default BoardConfigModal;
