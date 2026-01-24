import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Spinner, Button, TextareaControl } from '@wordpress/components';
import ReactMarkdown from 'react-markdown';

// Helper function to build the comment tree
const buildCommentTree = (comments) => {
    const commentMap = {};
    const tree = [];

    comments.forEach(comment => {
        commentMap[comment.id] = { ...comment, children: [] };
    });

    comments.forEach(comment => {
        if (comment.parent_id && commentMap[comment.parent_id]) {
            commentMap[comment.parent_id].children.push(commentMap[comment.id]);
        } else {
            tree.push(commentMap[comment.id]);
        }
    });

    // Sort top-level comments and their children by created_at
    const sortComments = (commentsArray) => {
        commentsArray.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
        commentsArray.forEach(comment => {
            if (comment.children.length > 0) {
                sortComments(comment.children);
            }
        });
    };

    sortComments(tree);
    return tree;
};

// Custom component to render text nodes with @mention highlighting
const TextWithMentions = ({ children }) => {
    if (typeof children !== 'string') {
        return children;
    }

    const parts = [];
    let lastIndex = 0;
    const mentionRegex = /@([a-zA-Z0-9_-]+)/g;
    let match;

    while ((match = mentionRegex.exec(children)) !== null) {
        // Add text before the mention
        if (match.index > lastIndex) {
            parts.push(children.substring(lastIndex, match.index));
        }
        // Add the mention span
        parts.push(
            <span key={match.index} style={{ fontWeight: 'bold', color: '#007cba' }}>
                {match[0]}
            </span>
        );
        lastIndex = match.index + match[0].length;
    }

    // Add remaining text
    if (lastIndex < children.length) {
        parts.push(children.substring(lastIndex));
    }

    return parts.length > 0 ? <>{parts}</> : children;
};

// Custom Markdown components to handle @mentions in all text nodes
const markdownComponents = {
    p: ({ children }) => <p><TextWithMentions>{children}</TextWithMentions></p>,
    li: ({ children }) => <li><TextWithMentions>{children}</TextWithMentions></li>,
    strong: ({ children }) => <strong><TextWithMentions>{children}</TextWithMentions></strong>,
    em: ({ children }) => <em><TextWithMentions>{children}</TextWithMentions></em>,
};

// Formatting help hint component
const FormattingHint = () => (
    <small style={{ color: '#666', display: 'block', marginTop: '4px', marginBottom: '8px' }}>
        Supports Markdown: **bold**, *italic*, `code`, [link](url), @username for mentions
    </small>
);


// Recursive Comment Item Component
const CommentItem = ({ comment, onReply, onDelete, onEdit, editingCommentId, onCancelEdit, onSaveEdit, editingBody, onEditBodyChange }) => {
    const depthPadding = comment.parent_id ? '20px' : '0px'; // Visual indent for replies
    const isEditing = editingCommentId === comment.id; // Check if THIS comment is being edited

    return (
        <li key={comment.id} style={{ marginBottom: '10px', padding: '8px', background: '#f9f9f9', borderRadius: '4px', marginLeft: depthPadding }}>
            {isEditing ? (
                <form onSubmit={(e) => { e.preventDefault(); onSaveEdit(comment.id); }}>
                    <TextareaControl
                        value={editingBody}
                        onChange={onEditBodyChange}
                    />
                    <FormattingHint />
                    <div style={{ display: 'flex', gap: '8px' }}>
                        <Button isPrimary type="submit">Save</Button>
                        <Button isSecondary onClick={onCancelEdit}>Cancel</Button>
                    </div>
                </form>
            ) : (
                <>
                    <div className="comment-body" style={{ marginBottom: '8px' }}>
                        <ReactMarkdown components={markdownComponents}>
                            {comment.body}
                        </ReactMarkdown>
                    </div>
                    <small>Commented on {new Date(comment.created_at).toLocaleString()}</small>
                    <div style={{ marginTop: '5px', display: 'flex', gap: '10px' }}>
                        <Button isLink onClick={() => onReply(comment.id)}>Reply</Button>
                        <Button isLink onClick={() => onEdit(comment)}>Edit</Button>
                        <Button isLink isDestructive onClick={() => onDelete(comment.id)}>Delete</Button>
                    </div>
                </>
            )}
            {comment.children.length > 0 && (
                <ul style={{ listStyle: 'none', padding: 0, marginTop: '10px' }}>
                    {comment.children.map(childComment => (
                        <CommentItem
                            key={childComment.id}
                            comment={childComment}
                            onReply={onReply}
                            onDelete={onDelete}
                            onEdit={onEdit}
                            editingCommentId={editingCommentId}
                            onCancelEdit={onCancelEdit}
                            onSaveEdit={onSaveEdit}
                            editingBody={editingBody}
                            onEditBodyChange={onEditBodyChange}
                        />
                    ))}
                </ul>
            )}
        </li>
    );
};


const CommentThread = ({ taskId }) => {
    const [comments, setComments] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);
    const [newComment, setNewComment] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [replyingTo, setReplyingTo] = useState(null); // Stores parent_id for replies

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
                parent_id: replyingTo, // Include parent_id if replying
            },
        })
            .then((newlyAddedComment) => {
                setComments([...comments, newlyAddedComment]);
                setNewComment('');
                setReplyingTo(null); // Clear replyingTo state
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

    const handleUpdateComment = (commentId) => {
        // e.preventDefault(); // Handled by CommentItem for inner form

        apiFetch({
            path: `/es-scrum/v1/comments/${commentId}`,
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

    const handleReplyClick = (commentId) => {
        setReplyingTo(commentId);
        // Optionally scroll to comment form
    };

    if (isLoading) {
        return <Spinner />;
    }

    if (error) {
        return <div className="notice notice-error"><p>Error: {error}</p></div>;
    }

    const threadedComments = buildCommentTree(comments);

    return (
        <div className="comment-thread">
            <h4>Comments</h4>
            {threadedComments.length === 0 ? (
                <p>No comments yet.</p>
            ) : (
                <ul style={{ listStyle: 'none', padding: 0 }}>
                    {threadedComments.map(comment => (
                        <CommentItem
                            key={comment.id}
                            comment={comment}
                            onReply={handleReplyClick}
                            onDelete={handleDeleteComment}
                            onEdit={startEditing}
                            editingCommentId={editingCommentId}
                            onCancelEdit={cancelEditing}
                            onSaveEdit={handleUpdateComment}
                            editingBody={editingCommentBody}
                            onEditBodyChange={(value) => setEditingCommentBody(value)}
                        />
                    ))}
                </ul>
            )}

            <form onSubmit={handleCommentSubmit}>
                <TextareaControl
                    label={replyingTo ? `Replying to comment ${replyingTo}` : "Add a comment"}
                    value={newComment}
                    onChange={(value) => setNewComment(value)}
                    disabled={isSubmitting}
                />
                <FormattingHint />
                <div style={{ display: 'flex', gap: '8px' }}>
                    <Button isPrimary type="submit" disabled={isSubmitting}>
                        {isSubmitting ? 'Submitting...' : 'Submit Comment'}
                    </Button>
                    {replyingTo && <Button isSecondary onClick={() => setReplyingTo(null)}>Cancel Reply</Button>}
                </div>
            </form>
        </div>
    );
};

export default CommentThread;
