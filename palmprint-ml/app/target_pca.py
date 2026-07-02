# app/target_pca.py
class TargetPCA:
    def __init__(self, n_components, explained_variance_ratio_):
        self.n_components_ = n_components
        self.explained_variance_ratio_ = explained_variance_ratio_
        
class ProductionPCA:
    """Wrapper agar pca_99 (fitted) + slicing n_components tersimpan sebagai 1 objek utuh."""
    def __init__(self, base_pca, n_components):
        self.base_pca = base_pca
        self.n_components_ = n_components

    def transform(self, X):
        return self.base_pca.transform(X)[:, :self.n_components_]