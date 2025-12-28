### DC-34: Heatmap Logic and Interpretation Documentation

**1. Data Aggregation Strategy (Backend Logic):**
To calculate the "Intensity Score" for each cell, we will use a weighted system:
- High Priority Task: 3 points
- Task in "Review" or "In Progress": 2 points
- Task in "To-Do": 1 point

**2. Visualization Guide (Color Gradients):**
- 0-5 Points: Light Green (Stable)
- 6-10 Points: Yellow (Increasing Load)
- 11+ Points: Dark Red (High Intensity/Bottleneck)

**3. User Controls (Switching Modes):**
- Toggle 1: View by Priority (Focus on Urgent/Normal distribution).
- Toggle 2: View by User (Identify overworked team members).
- Toggle 3: View by Column (Identify where tasks are getting stuck).

**4. Real-time Update Logic:**
The heatmap must recalculate scores automatically whenever a task is moved between columns or a priority tag is changed.
