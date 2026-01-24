import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Spinner, Button, TextareaControl } from '@wordpress/components';

const CommentThread = ({ taskId }) => {
    const [comments, setComments] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);
    const [newComment, setNewComment] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [editingCommentId, setEditingCommentId] = useState(null);
    const [editingCommentBody, setEditingCommentBody] = useState('');

    const fetchComments = () => {
        if (!taskId) {
            setIsLoading(false);
            return;
        }
        setIsLoading(true);
        apiFetch({ path: `/es-scrum/v1/comments?task_id=${taskId}` })
            .then((data) => {
                setComments(data);
                setIsLoading(false);
            })
            .catch((err) => {
                setError(err.message);
                setIsLoading(false);
            });
    };

    useEffect(() => {
        fetchComments();
    }, [taskId]);

    const handleCommentSubmit = (e) => {
        e.preventDefault();
        if (!newComment.trim()) return;

        setIsSubmitting(true);
        apiFetch({
            path: '/es-scrum/v1/comments',
            method: 'POST',
            data: {
                task_id: taskId,
                body: newComment,
            },
        })
        .then((newlyAddedComment) => {
            setComments([...comments, newlyAddedComment]);
            setNewComment('');
            setIsSubmitting(false);
        })
        .catch((err) => {
            setError(err.message);
            setIsSubmitting(false);
        });
    };

    const handleDeleteComment = (commentId) => {
        if (!window.confirm('Are you sure you want to delete this comment?')) {
            return;
        }

        apiFetch({
            path: `/es-scrum/v1/comments/${commentId}`,
            method: 'DELETE',
        })
        .then(() => {
            setComments(comments.filter(comment => comment.id !== commentId));
        })
        .catch((err) => {
            setError(err.message);
        });
    };

    const startEditing = (comment) => {
        setEditingCommentId(comment.id);
        setEditingCommentBody(comment.body);
    };

    const cancelEditing = () => {
        setEditingCommentId(null);
        setEditingCommentBody('');
    };

    const handleUpdateComment = (e) => {
        e.preventDefault();
        
        apiFetch({
            path: `/es-scrum/v1/comments/${editingCommentId}`,
            method: 'PATCH',
            data: {
                body: editingCommentBody,
            },
        })
        .then((updatedComment) => {
            setComments(comments.map(comment =>
                comment.id === updatedComment.id ? updatedComment : comment
            ));
            cancelEditing();
        })
        .catch((err) => {
            setError(err.message);
        });
    };

    if (isLoading) {
        return <Spinner />;
    }

    if (error) {
        return <div className="notice notice-error"><p>Error: {error}</p></div>;
    }

    return (
        <div className="comment-thread">
            <h4>Comments</h4>
            {comments.length === 0 ? (
                <p>No comments yet.</p>
            ) : (
                <ul style={{ listStyle: 'none', padding: 0 }}>
                    {comments.map(comment => (
                        <li key={comment.id} style={{ marginBottom: '10px', padding: '8px', background: '#f9f9f9', borderRadius: '4px' }}>
                            {editingCommentId === comment.id ? (
                                <form onSubmit={handleUpdateComment}>
                                    <TextareaControl
                                        value={editingCommentBody}
                                        onChange={(value) => setEditingCommentBody(value)}
                                    />
                                    <Button isPrimary type="submit">Save</Button>
                                    <Button isSecondary onClick={cancelEditing}>Cancel</Button>
                                </form>
                            ) : (
                                <>
                                    <p>{comment.body}</p>
                                    <small>Commented on {new Date(comment.created_at).toLocaleDateString()}</small>
                                    <div style={{ marginTop: '5px' }}>
                                        <Button isLink onClick={() => startEditing(comment)}>Edit</Button>
                                        <Button isLink isDestructive onClick={() => handleDeleteComment(comment.id)}>Delete</Button>
                                    </div>
                                </>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <form onSubmit={handleCommentSubmit}>
                <TextareaControl
                    label="Add a comment"
                    value={newComment}
                    onChange={(value) => setNewComment(value)}
                    disabled={isSubmitting}
                />
                <Button isPrimary type="submit" disabled={isSubmitting}>
                    {isSubmitting ? 'Submitting...' : 'Submit Comment'}
                </Button>
            </form>
        </div>
    );
};

export default CommentThread;
