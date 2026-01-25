# 📡 API Documentation

The EcoServants Digital Scrum Board exposes a REST API via the WordPress REST API infrastructure.

**Base URL:** `/wp-json/es-scrum/v1`

## Endpoints

### Tasks

#### Get All Tasks
Retrieves a list of all tasks.
```http
GET /tasks
```

#### Create a Task
Creates a new task.
```http
POST /tasks
Content-Type: application/json

{
  "title": "Fix Header Bug",
  "description": "Header is misaligned on mobile.",
  "status": "todo",
  "priority": "normal"
}
```

#### Update a Task
Updates a specific task by ID.
```http
PUT /tasks/:id
Content-Type: application/json

{
  "status": "in-progress"
}
```

#### Delete a Task
Deletes a task.
```http
DELETE /tasks/:id
```

### Sprints (Future)
*Endpoints for sprint management will be added here.*
