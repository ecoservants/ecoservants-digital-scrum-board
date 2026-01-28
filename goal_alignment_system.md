Our objective is to measure how intern work contributes to higher-level mission outcomes. 
Core Logic:
Goal Mapping: Admins create organizational goals (e.g., "Improve Community Engagement").
Contribution Metrics: Tasks are linked to one or more objectives, calculating a "contribution score".
Visualization: Progress indicators showing how much of a goal is completed.

Backend Logic (Python Implementation):
Simple Goal Alignment Checker,
def calculate_goal_progress(tasks, goal_id):
    relevant_tasks = [t for t in tasks if goal_id in t['linked_goals']]
    if not relevant_tasks: return 0
    
    completed_tasks = [t for t in relevant_tasks if t['status'] == 'Done']
    progress_percentage = (len(completed_tasks) / len(relevant_tasks)) * 100
    return progress_percentage

Example usage,
# my_tasks = [{'id': 1, 'linked_goals': [36], 'status': 'Done'}]
# print(f"Goal Progress: {calculate_goal_progress(my_tasks, 36)}%")
