// DC-30
import React, { useState, useEffect, useMemo, memo } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { Icon, arrowDown, arrowUp, cancelCircleFilled, chevronDown, chevronUp, pencil, plusCircle } from '@wordpress/icons';
import { Spinner } from '@wordpress/components';

// Memoized subtask individual items
const SubTasksItem = memo(({ index, checkedSubTasks, setCheckedSubTasks, item, setError, setHighestOrder, setSubtasks, highestOrder }) => {
    const [taskDetailActive, setTaskDetailActive] = useState(false);
    const [isHovered, setIsHovered] = useState(false);
    const [titleEditValue, setTitleEditValue] = useState(item?.title);
    const [notesEditValue, setNotesEditValue] = useState(item?.notes);
    const [isEditing, setIsEditing] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const checked = checkedSubTasks.includes(index);

    // We toggle the checkbox by adding/removing it to/from checkedSubTasks
    const toggleCheckClick = () => {
        apiFetch({
            path: `/es-scrum/v1/subtasks/${item.id}`,
            method: 'PATCH',
            data: {
                'is_completed': !checked,
            }
        })
            .then(() => {
                if (checkedSubTasks.includes(index)) {
                    setCheckedSubTasks(checkedSubTasks.filter((ind) => ind != index));
                } else {
                    setCheckedSubTasks([...checkedSubTasks, index]);
                }
            })
            .catch((err) => {
                console.log(err.message);
            })
    }
    // Toggle for the task accordian
    const toggleDetailActive = () => {
        !isEditing && setTaskDetailActive(state => !state);
    }
    useEffect(() => {
        if (!checkedSubTasks.includes(index)) {
            if (parseInt(item.is_completed) == 1) {
                setCheckedSubTasks((state) => [...state, index]);
            }
        }
        setHighestOrder(state => Math.max(state, item.sort_order));
    }, [])

    const reset = () => {
        setTitleEditValue(item?.title);
        setNotesEditValue(item?.notes);
        setIsSubmitting(false);
        setIsEditing(false);
    }

    const handleSubtaskEditSave = () => {
        setIsSubmitting(true);
        apiFetch(
            {
                path: `/es-scrum/v1/subtasks/${item.id}`,
                method: "PATCH",
                data: {
                    title: titleEditValue,
                    notes: notesEditValue
                }
            }
        )
            .then(() => {
                setIsSubmitting(false);
                setIsEditing(false);

            })
            .catch((err) => {
                reset();
                setError(err.message);

            })
    }

    // moves the sort order/task importance up by one
    const handleSortLevelUp = () => {
        if (parseInt(item.sort_order) === 0) return;

        apiFetch({
            path: `/es-scrum/v1/subtasks/${item.id}/levelup`,
            method: "POST",
        })
            .then(() => {
                setSubtasks(prev => {

                    const current = prev.find(t => t.id === item.id);
                    if (!current || parseInt(current.sort_order) === 0) return prev;

                    const above = prev.find(
                        t => parseInt(t.sort_order) === (parseInt(current.sort_order) - 1)
                    );

                    if (!above) return prev;

                    return prev
                        .map(t => {
                            if (t.id === current.id) {
                                return { ...t, sort_order: parseInt(t.sort_order) - 1 };
                            }
                            if (t.id === above.id) {
                                return { ...t, sort_order: parseInt(t.sort_order) + 1 };
                            }
                            return t;
                        })
                        .sort((a, b) => parseInt(a.sort_order) - parseInt(b.sort_order));
                });
            })
            .catch((err) => {
                setError(err.message);
            });
    };

    // moves the sort order/task importance down by one
    const handleSortLevelDown = () => {
        apiFetch({
            path: `/es-scrum/v1/subtasks/${item.id}/leveldown`,
            method: "POST",
        })
            .then(() => {
                setSubtasks(prev => {

                    const current = prev.find(t => t.id === item.id);
                    if (!current) return prev;

                    if (parseInt(current.sort_order) === highestOrder) return prev;

                    const below = prev.find(
                        t => parseInt(t.sort_order) === (parseInt(current.sort_order) + 1)
                    );

                    if (!below) return prev;

                    return prev
                        .map(t => {
                            if (t.id === current.id) {
                                return { ...t, sort_order: parseInt(t.sort_order) + 1 };
                            }
                            if (t.id === below.id) {
                                return { ...t, sort_order: parseInt(t.sort_order) - 1 };
                            }
                            return t;
                        })
                        .sort((a, b) => parseInt(a.sort_order) - parseInt(b.sort_order));
                });
            })
            .catch((err) => {
                setError(err.message);
            });
    };


    return (
        <>
            <li onMouseEnter={() => setIsHovered(true)} onMouseLeave={() => setIsHovered(false)} style={{ display: 'flex', alignItems: 'flex-end', width: '100%', cursor: 'pointer' }} onClick={toggleDetailActive}>
                <div style={{ width: '100%' }}>
                    {isEditing ?
                        <Icon onClick={() => { reset() }} icon={cancelCircleFilled} /> :
                        <input type='checkbox' checked={checked} onClick={(e) => {
                            e.stopPropagation();
                            toggleCheckClick();
                        }} />}

                    {isEditing ?
                        <input type='text' value={titleEditValue}
                            onChange={(e) => setTitleEditValue(e.target.value)}
                            placeholder={__("Enter SubTask Title...", 'es-scrum')}
                            style={{ border: 'none', borderBottom: '1px dashed black', borderRadius: '0', width: '100%', marginLeft: '10px', outline: 'none' }} /> :
                        <span style={checked ? { textDecoration: 'line-through' } : {}}>
                            {titleEditValue}
                        </span>
                    }
                </div>
                <div style={{ display: "flex" }}>
                    {(isHovered && !isEditing) &&
                        <>
                            <Icon icon={pencil} onClick={(e) => {
                                e.stopPropagation();
                                setIsEditing(true);
                            }} />
                            <Icon icon={arrowUp} onClick={(e) => {
                                e.stopPropagation();
                                handleSortLevelUp();
                            }} />
                            <Icon icon={arrowDown} onClick={(e) => {
                                e.stopPropagation();
                                handleSortLevelDown();
                            }} />
                        </>
                    }
                    {taskDetailActive ? <Icon icon={chevronUp} /> : <Icon icon={chevronDown} />}
                </div>
            </li>
            {(taskDetailActive || isEditing) && <div style={{ padding: '3px 0px 3px 10px' }}>
                <span style={{ fontWeight: 400 }}>{__('Notes: ', 'es-scrum')} {!isEditing && !item.notes && 'N/A'}</span>

                {(item.notes || isEditing) &&
                    <div style={{ padding: '0px 0px 0px 10px' }}>
                        {isEditing ?
                            <textarea type='text' value={notesEditValue}
                                onChange={(e) => setNotesEditValue(e.target.value)}
                                placeholder={__("Enter Extra Notes(optional)...", 'es-scrum')} style={{ border: 'none', resize: 'vertical', width: '100%', borderBottom: '1px dashed black', outline: 'none', borderRadius: '0', }} />
                            :
                            <label style={{ fontWeight: 300, textWrapMode: 'wrap' }}>{notesEditValue}</label>
                        }
                    </div>
                }
                {isEditing &&
                    <div style={{ width: '100%', display: 'flex', justifyContent: 'flex-end' }}>
                        <button className='components-button is-primary' onClick={() => { handleSubtaskEditSave() }}>{__((isSubmitting ? 'Saving' : 'Save'), 'es-scrum')}</button>
                    </div>
                }
            </div>}
        </>
    )
})

// Subtask Parent container
const SubTasksList = ({ parentTaskId }) => {
    const [checkedSubTasks, setCheckedSubTasks] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);
    const [highestOrder, setHighestOrder] = useState(-1);
    const [newSubTask, setNewSubTask] = useState({ title: '', notes: '' })
    const [subtasks, setSubtasks] = useState([]);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [addSubTaskOpen, setAddSubTaskOpen] = useState(false);


    const fetchSubTasks = () => {
        if (!parentTaskId) {
            setIsLoading(false);
            return;
        }
        setIsLoading(true);
        apiFetch({ path: `/es-scrum/v1/subtasks?parent_task=${parentTaskId}` })
            .then((data) => {
                setSubtasks(data.sort((a, b) => a.sort_order - b.sort_order));
                setIsLoading(false);
            })
            .catch((err) => {
                setError(err.message);
                setIsLoading(false);
            });
    };

    const handleNewSubTaskSubmit = () => {
        setIsSubmitting(true);
        if (!newSubTask.title.trim()) return;

        apiFetch({
            path: '/es-scrum/v1/subtasks',
            method: 'POST',
            data: {
                parent_task: parentTaskId,
                title: newSubTask.title,
                notes: newSubTask.notes,
                sort_order: highestOrder + 1,
            },
        })
            .then((resp) => {
                setSubtasks(state => [...state, resp]);
            })
            .error((err) => {
                setError(err.message);
            })
        setIsSubmitting(false);
        setNewSubTask({ title: '', notes: '' });
        setAddSubTaskOpen(false);
        setHighestOrder(prev => prev + 1);
    }

    useEffect(() => {
        fetchSubTasks();
    }, [parentTaskId]);

    const taskCompletionProgress = useMemo(() => {
        if (!checkedSubTasks || checkedSubTasks.length === 0) return 0;

        return ((checkedSubTasks.length / subtasks.length) * 100).toFixed(1);
    }, [checkedSubTasks, subtasks]);


    if (isLoading) {
        return <Spinner />;
    }

    if (error) {
        return <div className="notice notice-error"><p>Error: {error}</p></div>;
    }

    return (
        <>

            {
                // If there is no subtask available then we hide the entire display
                (subtasks.length != 0) &&
                <>

                    <h3>{__('Sub Tasks', 'es-scrum')}</h3>
                    <div>

                        <div
                            style={{
                                width: "100%",
                                background: "#f0f0f1",
                                height: "12px",
                                display: "flex",
                                borderRadius: "8px",
                                overflow: "hidden",
                                boxShadow: "inset 0 1px 3px rgba(0,0,0,0.15)"
                            }}
                        >
                            <div
                                style={{
                                    height: "100%",
                                    width: taskCompletionProgress + "%",
                                    background: "linear-gradient(90deg, #819d46, #408564, #2166ae, #195fa9)",
                                    borderRadius: "8px",
                                    transition: "width 0.4s ease"
                                }}
                            />
                        </div>
                        <span>{taskCompletionProgress}% {__('complete', 'es-scrum')}</span>
                    </div>
                    <div>

                        {subtasks.map((item, index) =>

                            <SubTasksItem key={item.id} setError={setError} setHighestOrder={setHighestOrder} highestOrder={highestOrder} checkedSubTasks={checkedSubTasks} item={item} setCheckedSubTasks={setCheckedSubTasks} setSubtasks={setSubtasks} index={index} />

                        )}
                        {!true && <>
                            <div style={{ display: 'flex', alignItems: 'flex-end', width: '100%', cursor: 'pointer' }}>
                                <div style={{ width: '100%' }}>
                                    <span style={{ fontWeight: 400 }}>{__('Title: ', 'es-scrum')} </span>
                                    <span><input type='text' value={"Strike Through"}
                                        placeholder={__("Enter SubTask Title...", 'es-scrum')} style={{ border: 'none', borderBottom: '1px dashed black', borderRadius: '0', width: '100%', marginLeft: '10px', outline: 'none' }} /></span>
                                </div>
                            </div>
                            <div style={{ padding: '3px 0px 3px 10px' }}>
                                <span style={{ fontWeight: 400 }}>{__('Notes: ', 'es-scrum')} </span>

                                <div style={{ padding: '0px 0px 0px 10px' }}>
                                    <textarea type='text' value={"newSubTask.notes"}
                                        placeholder={__("Enter Extra Notes(optional)...", 'es-scrum')} style={{ border: 'none', resize: 'vertical', width: '100%', borderBottom: '1px dashed black', outline: 'none', borderRadius: '0', }} />
                                </div>

                            </div>

                            <div style={{ width: '100%', display: 'flex', justifyContent: 'flex-end' }}>
                                <button className='components-button is-primary' onClick={handleNewSubTaskSubmit}>{__((isSubmitting ? 'Saving' : 'Save'), 'es-scrum')}</button>
                            </div>
                        </>
                        }

                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', cursor: 'pointer', gap: '8px', flexDirection: 'column' }}>

                        <div onClick={() => setAddSubTaskOpen(state => !state)} style={{ display: "flex", justifyContent: 'flex-end', cursor: 'pointer' }}>{addSubTaskOpen ? <Icon icon={cancelCircleFilled} /> : <Icon icon={plusCircle} />}</div>

                        {addSubTaskOpen &&
                            <>
                                <div style={{ display: 'flex', alignItems: 'self-end', gap: '10px' }}>
                                    <label>{__("Title", 'es-scrum')}: </label>
                                    <input type='text' value={newSubTask.title}
                                        disabled={isSubmitting}
                                        onChange={(e) => { setNewSubTask((state) => { return { ...state, 'title': e.target.value } }) }}
                                        placeholder={__("Enter SubTask Title...", 'es-scrum')} style={{ border: 'none', borderBottom: '1px dashed black', borderRadius: '0', width: '100%', marginLeft: '10px', outline: 'none' }} />
                                </div>
                                <div style={{ display: 'flex', alignItems: 'self-end', gap: '10px' }}>
                                    <label>{__('Notes', 'es-scrum')}: </label>
                                    <textarea type='text' value={newSubTask.notes}
                                        disabled={isSubmitting}
                                        onChange={(e) => { setNewSubTask((state) => { return { ...state, 'notes': e.target.value } }) }}
                                        placeholder={__("Enter Extra Notes(optional)...", 'es-scrum')} style={{ border: 'none', resize: 'vertical', width: '100%', borderBottom: '1px dashed black', outline: 'none', borderRadius: '0', }} />
                                </div>
                                <div style={{ width: '100%', display: 'flex', justifyContent: 'flex-end' }}>
                                    <button className='components-button is-primary' onClick={handleNewSubTaskSubmit}>{__((isSubmitting ? 'Saving' : 'Save'), 'es-scrum')}</button>
                                </div>
                            </>}
                    </div>

                </>
            }
        </>
    )

}

export default SubTasksList