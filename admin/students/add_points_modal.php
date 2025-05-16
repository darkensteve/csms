<!-- Add Points Modal -->
<div id="addPointsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="bg-gradient-to-r from-primary-700 to-primary-900 text-white px-6 py-4 rounded-t-lg flex items-center justify-between">
            <h3 class="text-lg font-semibold">Add Points</h3>
            <button type="button" class="text-white hover:text-gray-200" onclick="closeAddPointsModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="addPointsForm" method="POST">
            <div class="p-6">
                <input type="hidden" id="studentIdForPoints" name="student_id">
                <input type="hidden" id="idColForPoints" name="id_col">
                
                <div class="mb-4">
                    <label for="studentNameDisplay" class="block text-sm font-medium text-gray-700 mb-1">Student</label>
                    <div id="studentNameDisplay" class="bg-gray-50 border border-gray-300 rounded-md py-2 px-3 text-gray-700"></div>
                </div>
                
                <div class="mb-4">
                    <label for="points" class="block text-sm font-medium text-gray-700 mb-1">Points to Add</label>
                    <div class="flex items-center">
                        <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded-l" onclick="decrementPoints()">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" id="points" name="points" min="1" max="10" value="1" 
                               class="w-full text-center border-gray-300 focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50" 
                               required>
                        <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded-r" onclick="incrementPoints()">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">3 points = 1 additional session</p>
                </div>
                
                <div class="mb-4">
                    <label for="reason" class="block text-sm font-medium text-gray-700 mb-1">Reason (Optional)</label>
                    <textarea id="reason" name="reason" rows="3" 
                              class="w-full rounded-md border-gray-300 focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50"
                              placeholder="e.g., Excellent behavior, Helping other students, etc."></textarea>
                </div>
                
                <div class="flex items-center justify-end space-x-3 mt-6">
                    <button type="button" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300" onclick="closeAddPointsModal()">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">
                        Add Points
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddPointsModal(studentId, idCol, studentName) {
        // Set form action with GET parameters in the URL
        document.getElementById('addPointsForm').action = 'add_points.php?student_id=' + encodeURIComponent(studentId) + '&id_col=' + encodeURIComponent(idCol);
        
        // Also set hidden fields as a backup method
        document.getElementById('studentIdForPoints').value = studentId;
        document.getElementById('idColForPoints').value = idCol;
        
        document.getElementById('studentNameDisplay').textContent = studentName;
        document.getElementById('addPointsModal').classList.remove('hidden');
        document.getElementById('points').value = 1;
    }
    
    function closeAddPointsModal() {
        document.getElementById('addPointsModal').classList.add('hidden');
    }
    
    function incrementPoints() {
        const pointsInput = document.getElementById('points');
        const currentValue = parseInt(pointsInput.value) || 0;
        if (currentValue < 10) {
            pointsInput.value = currentValue + 1;
        }
    }
    
    function decrementPoints() {
        const pointsInput = document.getElementById('points');
        const currentValue = parseInt(pointsInput.value) || 0;
        if (currentValue > 1) {
            pointsInput.value = currentValue - 1;
        }
    }
    
    // Close modal when clicking outside
    document.getElementById('addPointsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAddPointsModal();
        }
    });
</script> 