-- First, create a backup of existing tasks
CREATE TABLE tasks_backup AS SELECT * FROM tasks;

-- Drop existing foreign key constraints
ALTER TABLE tasks DROP FOREIGN KEY tasks_ibfk_2;

-- Remove assigned_to column from tasks table
ALTER TABLE tasks DROP COLUMN assigned_to;

-- Create task_assignments table if it doesn't exist
CREATE TABLE IF NOT EXISTS task_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    employee_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY unique_task_employee (task_id, employee_id)
);

-- Migrate existing assignments to the new table
INSERT INTO task_assignments (task_id, employee_id)
SELECT task_id, assigned_to FROM tasks_backup
WHERE assigned_to IS NOT NULL;

-- Drop the backup table
DROP TABLE tasks_backup; 