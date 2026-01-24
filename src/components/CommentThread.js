import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Spinner, Button, TextareaControl } from '@wordpress/components';

const CommentThread = ({ taskId }) => {
    const [comments, setComments] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);
    const [newComment, setNewComment] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

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
                            <p>{comment.body}</p>
                            <small>Commented on {new Date(comment.created_at).toLocaleDateString()}</small>
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
