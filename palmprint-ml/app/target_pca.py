# app/target_pca.py
class TargetPCA:
    def __init__(self, n_components, explained_variance_ratio_):
        self.n_components_ = n_components
        self.explained_variance_ratio_ = explained_variance_ratio_